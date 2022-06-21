<?php

use Evoliz\Client\Config;
use Evoliz\Client\Exception\ResourceException;

require_once 'Class/EvolizSaleOrder.php';

abstract class Webhooks
{
    public static Config $config;

    /**
     * @param Config $config
     * @return void
     */
    public static function init(Config $config)
    {
        self::$config = $config;
        add_filter('woocommerce_checkout_fields', __CLASS__ . '::addNewFieldsToCheckout');
        add_action('woocommerce_checkout_create_order', __CLASS__ . '::fillOrderMetaData');
        add_action('woocommerce_new_order', __CLASS__ . '::createNewSaleOrder', 10, 2);
        add_action('woocommerce_order_status_changed', __CLASS__ . '::invoiceAndPaySaleOrder', 10, 3);
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
     * @param object $order Woocommerce order
     * @return void
     * @throws ResourceException
     */
    public static function createNewSaleOrder(int $orderId, object $order)
    {
        EvolizSaleOrder::create(self::$config, $order);
    }

    /**
     * @param int $orderId Woocommerce order identifier
     * @param string $previousStatus Previous status name
     * @param string $newStatus new status name
     * @return void
     * @throws ResourceException
     */
    public static function invoiceAndPaySaleOrder(int $orderId, string $previousStatus, string $newStatus)
    {
        if ($newStatus === "completed") {
            EvolizSaleOrder::invoiceAndPay(self::$config, $orderId);
        }
    }
}
