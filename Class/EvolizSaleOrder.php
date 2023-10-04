<?php

use Evoliz\Client\Config;
use Evoliz\Client\Exception\ResourceException;
use Evoliz\Client\Model\Item;
use Evoliz\Client\Model\Sales\SaleOrder;
use Evoliz\Client\Repository\Sales\SaleOrderRepository;

require_once 'EvolizClient.php';
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
    public static function findOrCreate(Config $config, object $order)
    {
        clearLog();

        $saleOrderRepository = new SaleOrderRepository($config);

        $matchingSaleOrders = $saleOrderRepository->list(['search' => (string) $order->get_order_key()]);

        $orderId = $order->get_order_number();

        if (empty($matchingSaleOrders->data)) {
            writeLog("[ Order : $orderId ] Creating the Sale Order from WooCommerce to Evoliz...");
            try {
                $clientId = EvolizClient::findOrCreate($config, $order);

                if (!$clientId) {
                    throw new Exception('Error finding or creating client.');
                }

                $contactId = EvolizContactClient::findOrCreate($config, $order, $clientId);

                $items = self::extractItemsFromOrder($order);

                $saleOrderRepository = new SaleOrderRepository($config);

                $date = new DateTime($order->get_date_created()->date);

                $newSaleOrder = [
                    'external_document_number' => (string) $order->get_order_number(),
                    'documentdate' => $date->format('Y-m-d'),
                    'clientid' => $clientId,
                    'contactid' => $contactId,
                    'object' => "Sale Order created from Woocommerce",
                    'term' => [
                        'paytermid' => 1,
                    ],
                    'items' => $items,
                    'comment' => $order->get_customer_note(),
                ];

                if (EvolizClient::isProfessional($config, $clientId)) {
                    $newSaleOrder['term']['recovery_indemnity'] = true;
                }

                $saleOrder = $saleOrderRepository->create(new SaleOrder($newSaleOrder));

                writeLog("[ Order : $orderId ] The Sale Order has been successfully created ($saleOrder->orderid).\n");

                $order->update_meta_data('EVOLIZ_CORDERID', $saleOrder->orderid);
                $order->save();
            } catch (Exception $exception) {
                writeLog("[ Order : $orderId ] " . $exception->getMessage() . "\n", $exception->getCode(), EVOLIZ_LOG_ERROR);
            }
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
    public static function invoiceAndPay(Config $config, object $wcOrder, bool $save = true)
    {
        $wcOrderId = $wcOrder->get_order_number();
        $evolizOrderId = $wcOrder->get_meta('EVOLIZ_CORDERID', true);

        try {
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
        } catch (Exception $exception) {
            writeLog("[ Order : $wcOrderId ] " . $exception->getMessage() . "\n", $exception->getCode(), EVOLIZ_LOG_ERROR);
        }
    }

    /**
     * @param object $order Woocommerce order
     *
     * @return array Array of Items filled in with the order data
     */
    private static function extractItemsFromOrder(object $order): array
    {
        $items = [];

        $orderId = (string) $order->get_order_number();
        writeLog("[ Order : $orderId ] Retrieving related items lines...");

        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            $productName = $product->get_name();
            $quantity = $item->get_quantity();
            writeLog("[ Order : $orderId ] Adding product '$productName' (x$quantity) to the Sale Order...");

            $priceExcludingTax = wc_get_price_excluding_tax($product);
            $unit_vat_exclude = get_option('woocommerce_prices_include_tax') === 'yes' ? $priceExcludingTax : $product->get_regular_price();
            $newItem = [
                'designation' => $productName,
                'quantity' => $quantity,
                'unit_price_vat_exclude' => round($unit_vat_exclude, 2),
            ];

            if (($product->get_sale_price() != null) && round($product->get_regular_price(), 2) > $product->get_sale_price()) {
                if (get_option('woocommerce_prices_include_tax') !== 'yes') {
                    $newItem['rebate'] = round(((float)$unit_vat_exclude - (float)$product->get_sale_price()) * $quantity, 2);
                } else {
                    $newItem['rebate'] = 0;
                    $fakeRebate = (float) $product->get_regular_price() - (float) $product->get_sale_price();
                    $newItem['designation'] .= ' (dont ' . $fakeRebate . ' € de remise TTC)';
                    $newItem['unit_price_vat_exclude'] = round($priceExcludingTax, 2);
                }
            }

            $hasTaxes = $item->get_subtotal_tax() !== null && $item->get_subtotal_tax() > 0;
            if ($hasTaxes) {
                $tax = new WC_Tax();
                $taxes = $tax->get_rates($product->get_tax_class());
                $rates = array_shift($taxes);
                $newItem['vat_rate'] = $rates['rate'];
            }

            $items[] = new Item($newItem);
        }

        self::addFeesToItems($order, $items);
        self::addShippingCostsToItems($order, $items);
        self::addGlobalRebateToItems($order, $items);

        return $items;
    }

    /**
     * @param object $order Woocommerce order
     * @param array $items Array of Items
     *
     * @return void
     */
    private static function addShippingCostsToItems(object $order, array &$items)
    {
        if ($order->get_shipping_total() !== null && $order->get_shipping_total() > 0) {
            $orderId = (string) $order->get_order_number();
            $unitPrice = round($order->get_shipping_total(), 2);
            writeLog("[ Order : $orderId ] Add a shipping costs line ($unitPrice) to the Sale Order...");

            $shipping = [
                'designation' => 'Frais de livraison',
                'quantity' => 1,
                'unit_price_vat_exclude' => $unitPrice,
            ];

            $shipping['vat_rate'] = 0;
            foreach( $order->get_items('tax') as $item_tax ){
                $tax_data = $item_tax->get_data();
                if ($tax_data['shipping_tax_total'] ===  $order->get_shipping_tax()) {
                    $shipping['vat_rate'] =  $tax_data['rate_percent'];
                }
            }

            $items[] = new Item($shipping);
        }
    }

    /**
     * @param object $order Woocommerce order
     * @param array $items Array of Items
     *
     * @return void
     */
    private static function addFeesToItems(object $order, array &$items)
    {
        $orderId = (string) $order->get_order_number();

        foreach ($order->get_items('fee') as $item) {
            writeLog("[ Order : $orderId ] Add a fee line to the Sale Order...");

            $priceTotal = $item->get_total() + $item->get_total_tax();
            $newItem = [
                'designation' => $item->get_name(),
                'quantity' => $item->get_quantity(),
                'unit_price_vat_exclude' => round($item->get_total(), 2),
            ];

            $hasTaxes = $item->get_total_tax() !== null && $item->get_total_tax() > 0;

            if ($hasTaxes) {
                $vat_rate = ($priceTotal - $item->get_total()) / $item->get_total() * 100;
                $newItem['vat_rate'] = round($vat_rate, 2);
            }

            $items[] = new Item($newItem);
        }
    }

    /**
     * @param object $order Woocommerce order
     * @param array $items Array of Items
     *
     * @return void
     */
    private static function addGlobalRebateToItems(object $order, array &$items)
    {
        if ($order->get_discount_total() !== null && $order->get_discount_total() > 0) {
            $orderId = (string) $order->get_order_number();
            $unitPrice = -round(($order->get_discount_total() + $order->get_discount_tax()), 2);
            writeLog("[ Order : $orderId ] Add a global rebate line ($unitPrice) to the Sale Order...");

            $rebate = [
                'designation' => 'Remise globale',
                'quantity' => 1,
                'unit_price_vat_exclude' => $unitPrice,
            ];

            $items[] = new Item($rebate);
        }
    }
}
