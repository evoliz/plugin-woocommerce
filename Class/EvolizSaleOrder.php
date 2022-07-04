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
     * @throws ResourceException|Exception
     */
    public static function findOrCreate(Config $config, object $order)
    {
        clearLog();

        $saleOrderRepository = new SaleOrderRepository($config);

        $matchingSaleOrders = $saleOrderRepository->list(['search' => (string) $order->get_order_key()]);

        $orderId = $order->get_id();

        if (empty($matchingSaleOrders->data)) {
            writeLog("[ Order : $orderId ] Creating the Sale Order from WooCommerce to Evoliz...");
            try {
                $clientId = EvolizClient::findOrCreate($config, $order);
                $contactId = EvolizContactClient::findOrCreate($config, $order, $clientId);

                $items = self::extractItemsFromOrder($order);

                $saleOrderRepository = new SaleOrderRepository($config);

                $date = new DateTime($order->get_date_created()->date);

                $newSaleOrder = [
                    'external_document_number' => (string) $order->get_order_key(),
                    'documentdate' => $date->format('Y-m-d'),
                    'clientid' => $clientId,
                    'contactid' => $contactId,
                    'object' => "Sale Order created from Woocommerce",
                    'term' => [
                        'paytermid' => 1,
                    ],
                    'items' => $items,
                    'comment' => $order->get_customer_note()
                ];

                if (EvolizClient::isProfessional($config, $clientId)) {
                    $newSaleOrder['term']['recovery_indemnity'] = true;
                }

                $saleOrder = $saleOrderRepository->create(new SaleOrder($newSaleOrder));
                writeLog("[ Order : $orderId ] The Sale Order has been successfully created ($saleOrder->orderid).\n");

//                $order->update_meta_data('evoliz', esc_attr(htmlspecialchars((string) $saleOrder)));
//                foreach ($order->get_meta_data() as $metaData) {
//                    if ($metaData->key === 'evoliz') {
//                        $toto = $metaData->value;
//                        writeLog("[ ifcbeuzfbuhzebfuhezhbfuhezhbfuhezh ] The Sale Order  ($toto).\n");
//                    }
//                }

            } catch (Exception $exception) {
                writeLog("[ Order : $orderId ] " . $exception->getMessage() . "\n", $exception->getCode(), EVOLIZ_LOG_ERROR);
            }
        }
    }

    /**
     * @param Config $config Configuration for API usage
     * @param object $wcOrder Woocommerce order
     * @param bool $save Precise whether to save the invoice or to keep it as draft
     * @return void
     * @throws ResourceException|Exception
     */
    public static function invoiceAndPay(Config $config, object $wcOrder, bool $save = true)
    {
        $wcOrderId = $wcOrder->get_id();

        $saleOrderRepository = new SaleOrderRepository($config);

        $matchingSaleOrders = $saleOrderRepository->list(['search' => (string) $wcOrder->get_order_key()]);

        if (!empty($matchingSaleOrders->data) && $matchingSaleOrders->data[0]->status !== 'invoice') {
            try {
                writeLog("[ Order : $wcOrderId ] Payment received. Creation of the Invoice...");
                $invoice = $saleOrderRepository->invoice($matchingSaleOrders->data[0]->orderid, $save);
                $invoiceId = $invoice->invoiceid;
                writeLog("[ Order : $wcOrderId ] The Sale Order has been successfully invoiced ($invoiceId).");

                writeLog("[ Order : $wcOrderId ] Creation of the Payment...");
                $payment = EvolizInvoice::pay($config, $invoiceId, EvolizPayment::getPayTypeId($wcOrder->get_payment_method()));
                writeLog("[ Order : $wcOrderId ] The Payment has been successfully created ($payment->paymentid).\n");
            } catch (Exception $exception) {
                writeLog("[ Order : $wcOrderId ] " . $exception->getMessage() . "\n", $exception->getCode(), EVOLIZ_LOG_ERROR);
            }
        }
    }

    /**
     * @param object $order Woocommerce order
     * @return array Array of Items filled in with the order data
     */
    private static function extractItemsFromOrder(object $order): array
    {
        $items = [];

        $orderId = (string) $order->get_id();
        writeLog("[ Order : $orderId ] Retrieving related items lines...");

        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            $productName = $product->get_name();
            $quantity = $item->get_quantity();
            writeLog("[ Order : $orderId ] Adding product '$productName' (x$quantity) to the Sale Order...");

            $wcTax = WC_Tax::get_base_tax_rates($product->get_tax_class());
            if (!empty($wcTax)) {
                $wcTax = reset($wcTax);
            }

            $newItem = [
                'designation' => $productName,
                'quantity' => $quantity,
                'unit_price_vat_exclude' => round($product->get_regular_price(), 2)
            ];

            if (($product->get_sale_price() !== null && $product->get_sale_price() > 0) && round($product->get_regular_price(), 2) > $product->get_sale_price()) {
                $newItem['rebate'] = ($product->get_regular_price() - $product->get_sale_price()) * $quantity;
            }

            if ($item->get_subtotal_tax() !== null && $item->get_subtotal_tax() > 0) {
                $newItem['vat_rate'] = $wcTax["rate"];
            }

            $items[] = new Item($newItem);
        }

        self::addShippingCostsToItems($order, $items);
        self::addGlobalRebateToItems($order, $items);

        return $items;
    }

    /**
     * @param object $order Woocommerce order
     * @param array $items Array of Items
     * @return void
     */
    private static function addShippingCostsToItems(object $order, array &$items)
    {
        if ($order->get_shipping_total() !== null && $order->get_shipping_total() > 0) {

            $orderId = (string) $order->get_id();
            $unitPrice = round($order->get_shipping_total(), 2);
            writeLog("[ Order : $orderId ] Add a shipping costs line ($unitPrice) to the Sale Order...");

            $shipping = [
                'designation' => 'Frais de livraison',
                'quantity' => 1,
                'unit_price_vat_exclude' => $unitPrice
            ];

            if ($order->get_shipping_tax() > 0) {
                $shipping['vat_rate'] = (float) ($order->get_shipping_tax() / $order->get_shipping_total() * 100);
            }

            $items[] = new Item($shipping);
        }
    }

    /**
     * @param object $order Woocommerce order
     * @param array $items Array of Items
     * @return void
     */
    private static function addGlobalRebateToItems(object $order, array &$items)
    {
        if ($order->get_discount_total() !== null && $order->get_discount_total() > 0) {

            $orderId = (string) $order->get_id();
            $unitPrice = - round(($order->get_discount_total() + $order->get_discount_tax()), 2);
            writeLog("[ Order : $orderId ] Add a global rebate line ($unitPrice) to the Sale Order...");

            $rebate = [
                'designation' => 'Remise globale',
                'quantity' => 1,
                'unit_price_vat_exclude' => $unitPrice
            ];

            $items[] = new Item($rebate);
        }
    }
}
