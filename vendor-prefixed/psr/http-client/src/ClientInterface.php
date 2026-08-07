<?php

namespace Evoliz\WooCommerce\Vendor\Psr\Http\Client;

use Evoliz\WooCommerce\Vendor\Psr\Http\Message\RequestInterface;
use Evoliz\WooCommerce\Vendor\Psr\Http\Message\ResponseInterface;

interface ClientInterface
{
    /**
     * Sends a PSR-7 request and returns a PSR-7 response.
     *
     * @param RequestInterface $request
     *
     * @return ResponseInterface
     *
     * @throws \Evoliz\WooCommerce\Vendor\Psr\Http\Client\ClientExceptionInterface If an error happens while processing the request.
     */
    public function sendRequest(RequestInterface $request): ResponseInterface;
}
