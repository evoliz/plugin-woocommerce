<?php

abstract class EvolizPayment
{
    const PAYTYPES = [
        'bacs' => 2, // Direct bank transfer
        'woocommerce_payments' => 3, // @Todo : WooCommerce Payments ?
        'cheque' => 4, // Check payments
        'cod' => 5, // Cash on delivery
    ];

    /**
     * @param string $payTypeLabel PayType label from Woocommerce
     * @return int Evoliz Paytype ID
     */
    public static function getPayTypeId(string $payTypeLabel): int
    {
        if (array_key_exists($payTypeLabel, self::PAYTYPES)) {
            return self::PAYTYPES[$payTypeLabel];
        }
        return 6; // Other
    }

}
