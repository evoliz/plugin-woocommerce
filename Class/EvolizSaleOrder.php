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
     *
     * @return void
     * @throws ResourceException|Exception
     */
    public static function invoiceAndPay(Config $config, object $wcOrder, bool $save = true)
    {
        $wcOrderId = $wcOrder->get_order_number();

        $saleOrderRepository = new SaleOrderRepository($config);

        $matchingSaleOrders = $saleOrderRepository->list(['search' => (string) $wcOrder->get_order_number()]);

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

            $hasTaxes = $item->get_subtotal_tax() !== null && $item->get_subtotal_tax() > 0;
            if ($hasTaxes) {
                $tax = new WC_Tax();
                $taxes = $tax->get_rates($product->get_tax_class());
                $rates = array_shift($taxes);
                $newItem['vat_rate'] = $rates['rate'];
            }

            $items[] = new Item($newItem);
        }

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
}
