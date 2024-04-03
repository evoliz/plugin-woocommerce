<?php

use Evoliz\Client\Config;
use Evoliz\Client\Exception\ResourceException;
use Evoliz\Client\Model\Item;
use Evoliz\Client\Model\Sales\SaleOrder;
use Evoliz\Client\Repository\Sales\SaleOrderRepository;

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
    public static function findOrCreate(Config $config, object $order)
    {
        clearLog();

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

            $items = self::extractItemsFromOrder($order);

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
    public static function invoiceAndPay(Config $config, object $wcOrder, bool $save = true)
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

            $unit_vat_exclude = $item->get_subtotal() / $item->get_quantity();

            $newItem = [
                'designation' => $productName,
                'quantity' => $quantity,
                'unit_price_vat_exclude' => round($unit_vat_exclude, 2),
            ];

            if ($item->get_subtotal() != $item->get_total()) {
                $newItem['rebate'] = round($item->get_subtotal() - $item->get_total(), 2);
            }

            writeLog('Original item data: ' . json_encode($item->get_data()), null, EVOLIZ_LOG_DEBUG);

            $hasTaxes = $item->get_subtotal_tax() !== null && $item->get_subtotal_tax() > 0;
            if ($hasTaxes) {
                $tax = new WC_Tax();
                $taxes = $tax->get_rates($product->get_tax_class());
                $rates = array_shift($taxes);
                $newItem['vat_rate'] = $rates['rate'];

                writeLog('VAT data: ' . json_encode($rates), null, EVOLIZ_LOG_DEBUG);
            } else {
                writeLog('No VAT data for ' . $productName, null, EVOLIZ_LOG_DEBUG);
            }

            writeLog('Created item data: ' . json_encode($newItem), null, EVOLIZ_LOG_DEBUG);

            $items[] = new Item($newItem);
        }

        self::addFeesToItems($order, $items);
        self::addShippingCostsToItems($order, $items);

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

            $shipping['vat_rate'] = null;
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
}
