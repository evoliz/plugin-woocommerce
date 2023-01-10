<?php

use Evoliz\Client\Config;
use Evoliz\Client\Exception\ConfigException;
use Evoliz\Client\Exception\ResourceException;

require_once 'Class/EvolizSaleOrder.php';

abstract class Webhooks
{
    public static $config;

    /**
     * @param Config $config
     * @return void
     */
    public static function init(Config $config)
    {
        self::$config = $config;
        add_filter('woocommerce_checkout_fields', __CLASS__ . '::addNewFieldsToCheckout');
        add_action('woocommerce_checkout_create_order', __CLASS__ . '::fillOrderMetaData');
        add_action('woocommerce_order_status_changed', __CLASS__ . '::manageSaleOrder', 10, 3);
    }

    /**
     * @param array $fields
     * @return array
     */
    public static function addNewFieldsToCheckout(array $fields): array
    {
        if (get_option('wc_evz_enable_vat_number') === 'on') {
            if (get_option('wc_evz_eu_vat_number') === null || get_option('wc_evz_eu_vat_number') === '') {
                $fields['billing']['vat_number'] = [
                    'label' => __('EU VAT number', 'woocommerce'), // Add custom field label
                    'placeholder' => _x('EU VAT number', 'placeholder', 'woocommerce'), // Add custom field placeholder
                    'required' => false, // if field is required or not
                    'clear' => false, // add clear or not
                    'type' => 'text', // add field type
                    'class' => array('form-row-wide'),
                ];
            }
        }

        return $fields;
    }

    /**
     * @param object $order
     * @return void
     */
    public static function fillOrderMetaData(object $order) {
        if (!empty( $_POST['vat_number'])) {
            $order->update_meta_data('vat_number', esc_attr(htmlspecialchars($_POST['vat_number'])));
        }
    }

    /**
     * @param int $orderId Woocommerce order identifier
     * @param string $previousStatus Order previous status name
     * @param string $newStatus Order new status name
     * @return void
     * @throws ResourceException
     */
    public static function manageSaleOrder(int $orderId, string $previousStatus, string $newStatus)
    {
        $wcOrder = wc_get_order($orderId);

        try {
            switch ($newStatus) {
                case "on-hold" :
                    EvolizSaleOrder::findOrCreate(self::$config, $wcOrder);
                    break;
                case "processing" :
                    if ($previousStatus !== 'on-hold') {
                        EvolizSaleOrder::findOrCreate(self::$config, $wcOrder);
                    }

                    if ($wcOrder->get_payment_method() !== 'cod') {
                        EvolizSaleOrder::invoiceAndPay(self::$config, $wcOrder);
                    }
                    break;
                case "completed" :
                    if (!in_array($previousStatus, ['on-hold', 'processing'])) {
                        EvolizSaleOrder::findOrCreate(self::$config, $wcOrder);
                    }
                    EvolizSaleOrder::invoiceAndPay(self::$config, $wcOrder);
            }
        } catch (ConfigException $exception) {
            writeLog("[ Order : $orderId ] " . $exception->getMessage() . "\n", $exception->getCode(), EVOLIZ_LOG_ERROR);
        }
    }
}
