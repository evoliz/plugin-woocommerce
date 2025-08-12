<?php

use Evoliz\Client\Config;
use Evoliz\Client\Exception\ResourceException;
use Evoliz\Client\Model\Item;
use Evoliz\Client\Model\Sales\SaleOrder;
use Evoliz\Client\Repository\Sales\SaleOrderRepository;

require_once 'EvolizArticle.php';
require_once 'EvolizClient.php';
require_once 'EvolizClientDeliveryAddress.php';
require_once 'EvolizContactClient.php';
require_once 'EvolizInvoice.php';
require_once 'EvolizPayment.php';

abstract class EvolizSaleOrder
{
    /**
     * @param Config $config Configuration for API usage
     * @param object $order Woocommerce order
     *
     * @throws ResourceException|Exception
     */
    public static function findOrCreate(Config $config, object $order): void
    {
        clearLog();

        $prices_include_tax = get_option('woocommerce_prices_include_tax') === 'yes';

        $saleOrderRepository = new SaleOrderRepository($config);

        $matchingSaleOrders = $saleOrderRepository->list(['search' => (string) $order->get_order_key()]);

        $orderId = $order->get_order_number();

        if (empty($matchingSaleOrders->data)) {
            writeLog("[ Order : $orderId ] Creating the Sale Order from WooCommerce to Evoliz...");

            $client = EvolizClient::findOrCreate($config, $order);

            if (!$client) {
                throw new Exception('Error finding or creating client.');
            } else {
                $clientId = $client->clientid;
            }

            $clientAddressId = EvolizClientDeliveryAddress::findOrCreate($config, $order, $client);

            $contactId = EvolizContactClient::findOrCreate($config, $order, $clientId);

            $items = self::extractItemsFromOrder($order, $config, $prices_include_tax);

            $saleOrderRepository = new SaleOrderRepository($config);

            $date = new DateTime($order->get_date_created()->date);
            $orderObject = 'Commande n°' . $order->get_order_number() . ' sur ' . get_bloginfo();

            $newSaleOrder = [
                'external_document_number' => (string) $order->get_order_number(),
                'documentdate' => $date->format('Y-m-d'),
                'clientid' => $clientId,
                'contactid' => $contactId,
                'object' => $orderObject,
                'term' => [
                    'paytermid' => 1,
                ],
                'items' => $items,
                'comment' => $order->get_customer_note(),
                'prices_include_vat' => $prices_include_tax,
            ];

            if ($clientAddressId) {
                $newSaleOrder['delivery_addressid'] = $clientAddressId;
            }

            if (EvolizClient::isProfessional($config, $clientId)) {
                $newSaleOrder['term']['recovery_indemnity'] = true;
            }

            $saleOrder = $saleOrderRepository->create(new SaleOrder($newSaleOrder));

            writeLog("[ Order : $orderId ] The Sale Order has been successfully created ($saleOrder->orderid).\n\n");

            $order->update_meta_data('EVOLIZ_CORDERID', $saleOrder->orderid);
            $order->save();
        }
    }

    /**
     * @param Config $config Configuration for API usage
     * @param object $wcOrder Woocommerce order
     * @param bool $save Precise whether to save the invoice or to keep it as draft
     *
     * @return void
     * @throws ResourceException|Exception
     */
    public static function invoiceAndPay(Config $config, object $wcOrder, bool $save = true): void
    {
        $wcOrderId = $wcOrder->get_order_number();
        $evolizOrderId = $wcOrder->get_meta('EVOLIZ_CORDERID', true);

        if (empty($evolizOrderId)) {
            throw new Exception('Evoliz order id is missing in meta.');
        }

        $saleOrderRepository = new SaleOrderRepository($config);
        $evolizOrder = $saleOrderRepository->detail($evolizOrderId);

        if ($evolizOrder->status !== 'invoice') {
            writeLog("[ Order : $wcOrderId ] Payment received. Creation of the Invoice...");
            $invoice = $saleOrderRepository->invoice($evolizOrder->orderid, $save);
            $invoiceId = $invoice->invoiceid;
            writeLog("[ Order : $wcOrderId ] The Sale Order has been successfully invoiced ($invoiceId).");

            writeLog("[ Order : $wcOrderId ] Creation of the Payment...");
            $payment = EvolizInvoice::pay($config, $invoiceId, EvolizPayment::getPayTypeId($wcOrder->get_payment_method()));
            writeLog("[ Order : $wcOrderId ] The Payment has been successfully created ($payment->paymentid).\n");
        }
    }

    /**
     * @param object $order Woocommerce order
     *
     * @return array Array of Items filled in with the order data
     * @throws ResourceException
     */
    private static function extractItemsFromOrder(object $order, Config $config, bool $prices_include_tax = false): array
    {
        $orderId = (string) $order->get_order_number();
        $taxRates = self::getOrderTaxRates($order);
        $syncArticles = get_option('wc_evz_enable_articles_synchronization') === 'on';
        $items = [];

        writeLog("[ Order : $orderId ] Retrieving VAT data: " . json_encode($taxRates));
        writeLog("[ Order : $orderId ] Retrieving related items lines...");

        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            $productName = $product->get_name();
            $quantity = $item->get_quantity();

            writeLog("[ Order : $orderId ] Adding product '$productName' (x$quantity) to the Sale Order...");

            if ($prices_include_tax) {
                $unit_price = ($item->get_subtotal() + $item->get_subtotal_tax()) / $item->get_quantity();
            } else {
                $unit_price = $item->get_subtotal() / $item->get_quantity();
            }

            $newItem = [
                'designation' => $productName,
                'quantity' => $quantity,
                'unit_price' => round($unit_price, 2),
            ];

            if ($item->get_subtotal() != $item->get_total()) {
                $newItem['rebate'] = round($item->get_subtotal() - $item->get_total(), 2);
            }

            if (!empty($product->get_sku())) {
                $newItem['reference'] = $product->get_sku();
            }

            writeLog('Original item data: ' . json_encode($item->get_data()), null, EVOLIZ_LOG_DEBUG);

            $hasTaxes = $item->get_subtotal_tax() !== null && $item->get_subtotal_tax() > 0;

            if ($hasTaxes) {
                $percent = self::getTaxPercentageForOrderItem($item, $taxRates);
                $newItem['vat_rate'] = $percent;

                writeLog('VAT percent: ' . $percent, null, EVOLIZ_LOG_DEBUG);
            } else {
                writeLog('No VAT data for ' . $productName, null, EVOLIZ_LOG_DEBUG);
            }

            if ($syncArticles) {
                if ($product->get_sku()) {
                    $article = EvolizArticle::findOrCreate($config, $product);
                    $newItem['articleid'] = $article->articleid;
                } else {
                    writeLog("[ Order : $orderId ] The product $productName has no SKU, it cannot be created in Evoliz.", null, EVOLIZ_LOG_INFO);
                }
            }

            writeLog('Created item data: ' . json_encode($newItem), null, EVOLIZ_LOG_DEBUG);

            $items[] = new Item($newItem, $prices_include_tax);
        }

        self::addFeesToItems($order, $items, $prices_include_tax);
        self::addShippingCostsToItems($order, $items, $prices_include_tax);

        return $items;
    }

    /**
     * @param object $order Woocommerce order
     * @param array $items Array of Items
     *
     * @return void
     */
    private static function addShippingCostsToItems(object $order, array &$items, bool $prices_include_tax = false): void
    {
        if ($order->get_shipping_total() !== null && $order->get_shipping_total() > 0) {
            $orderId = (string) $order->get_order_number();
            $shipping_total = $order->get_shipping_total();
            $shipping_tax = $order->get_shipping_tax();

            if ($prices_include_tax) {
                $unitPrice = round($shipping_total + $shipping_tax, 2);
            } else {
                $unitPrice = round($shipping_total, 2);
            }

            writeLog("[ Order : $orderId ] Add a shipping costs line ($unitPrice) to the Sale Order...");

            $shipping = [
                'designation' => 'Frais de livraison',
                'quantity' => 1,
                'unit_price' => $unitPrice,
            ];

            $shipping['vat_rate'] = null;
            foreach ($order->get_items('tax') as $item_tax) {
                $tax_data = $item_tax->get_data();
                if ($tax_data['shipping_tax_total'] === $shipping_tax) {
                    $shipping['vat_rate'] =  $tax_data['rate_percent'];
                }
            }

            $items[] = new Item($shipping, $prices_include_tax);
        }
    }

    /**
     * @param object $order Woocommerce order
     * @param array $items Array of Items
     *
     * @return void
     */
    private static function addFeesToItems(object $order, array &$items, bool $prices_include_tax = false): void
    {
        $orderId = (string) $order->get_order_number();
        $taxRates = self::getOrderTaxRates($order);

        foreach ($order->get_items('fee') as $item) {
            writeLog("[ Order : $orderId ] Add a fee line to the Sale Order...");

            $total = $item->get_total();
            $tax = $item->get_total_tax();

            if ($prices_include_tax) {
                $unitPrice = round($total + $tax, 2);
            } else {
                $unitPrice = round($total, 2);
            }

            $newItem = [
                'designation' => $item->get_name(),
                'quantity' => $item->get_quantity(),
                'unit_price' => $unitPrice,
            ];

            $hasTaxes = $tax > 0;

            if ($hasTaxes) {
                $percent = self::getTaxPercentageForOrderItem($item, $taxRates);
                $newItem['vat_rate'] = $percent;

                writeLog('VAT percent: ' . $percent, null, EVOLIZ_LOG_DEBUG);
            } else {
                writeLog('No VAT data for fee line ' . $item->get_name(), null, EVOLIZ_LOG_DEBUG);
            }

            $items[] = new Item($newItem, $prices_include_tax);
        }
    }

    /**
     * See https://stackoverflow.com/a/78218963/1320311
     * for more information on how to handle product taxes.
     */
    private static function getOrderTaxRates($order): array
    {
        $taxRates = [];

        foreach ($order->get_items('tax') as $item) {
            $taxRates[$item->get_rate_id()] = $item->get_rate_percent();
        }

        return $taxRates;
    }

    private static function getTaxPercentageForOrderItem($item, $taxRates)
    {
        // Always use "total" because for fees "subtotal" never exists
        $item_taxes = $item->get_taxes();
        $tax_rate_id = key(array_filter($item_taxes['total']));

        return $taxRates[$tax_rate_id] ?? 0;
    }
}
