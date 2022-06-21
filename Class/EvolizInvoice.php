<?php

use Evoliz\Client\Config;
use Evoliz\Client\Exception\ResourceException;
use Evoliz\Client\Repository\Sales\InvoiceRepository;
use Evoliz\Client\Response\Sales\PaymentResponse;

abstract class EvolizInvoice
{
    /**
     * @param Config $config Configuration for API usage
     * @param int $invoiceId Invoice identifier
     * @param int $payTypeId PayType identifier
     * @return PaymentResponse|string
     * @throws ResourceException|Exception
     */
    public static function pay(Config $config, int $invoiceId, int $payTypeId)
    {
        $invoiceRepository = new InvoiceRepository($config);

        $invoice = $invoiceRepository->detail($invoiceId);
        return $invoiceRepository->pay($invoice->invoiceid, 'Payment from WooCommerce', $payTypeId, $invoice->total->net_to_pay);
    }
}
