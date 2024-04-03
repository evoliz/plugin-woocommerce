<?php

use Evoliz\Client\Config;
use Evoliz\Client\Exception\ResourceException;
use Evoliz\Client\Model\Clients\Client\ClientDeliveryAddress;
use Evoliz\Client\Repository\Clients\ClientDeliveryAddressRepository;

abstract class EvolizClientDeliveryAddress
{
    /**
     * @throws ResourceException|Exception
     */
    public static function findOrCreate(Config $config, object $order, object $client): ?int
    {
        $clientDeliveryAddressData = [];

        writeLog("[ Client delivery for client : $client->name ] Search for a match between the Client delivery address and the Evoliz database...");

        $clientDeliveryAddressRepository = new ClientDeliveryAddressRepository($config);

        $clientDeliveryAddresses = $clientDeliveryAddressRepository->list(['clientid' => $client->clientid]);

        if (!empty($clientDeliveryAddresses->data)) {
            foreach ($clientDeliveryAddresses->data as $clientDeliveryAddress) {
                if (
                    $clientDeliveryAddress->addr === $order->get_shipping_address_1()
                    && $clientDeliveryAddress->addr2 === $order->get_shipping_address_2()
                    && $clientDeliveryAddress->postcode === $order->get_shipping_postcode()
                    && $clientDeliveryAddress->town === $order->get_shipping_city()
                    && $clientDeliveryAddress->country->iso2 === $order->get_shipping_country()
                ) {
                    $deliveryAddressId = $clientDeliveryAddress->addressid;
                    writeLog("[ Client delivery address : $clientDeliveryAddress->name ] Match found with the Evoliz database ($deliveryAddressId).");
                }
            }
        }

        if (!isset($deliveryAddressId)) {
            try {
                writeLog("[ Client delivery address for client : $client->name ] No match found. Creating the Client delivery address from WooCommerce to Evoliz...");

                if (
                    $order->get_shipping_postcode() &&
                    $order->get_shipping_city() &&
                    $order->get_shipping_country()
                ) {
                    $clientDeliveryAddressData = array_filter([
                        "clientid" => $client->clientid,
                        "name" => 'Addresse de livraison',
                        "type" => 'delivery',
                        "postcode" => $order->get_shipping_postcode(),
                        'town' => $order->get_shipping_city(),
                        'iso2' => $order->get_shipping_country(),
                        'addr' => $order->get_shipping_address_1(),
                        'addr2' => $order->get_shipping_address_2()
                    ], function ($value) {
                        return isset($value) && $value !== '';
                    });
                }

                $newClientDeliveryAddress = $clientDeliveryAddressRepository->create(new ClientDeliveryAddress($clientDeliveryAddressData));
                $deliveryAddressId = $newClientDeliveryAddress->addressid;

                writeLog("[ Client delivery address : $newClientDeliveryAddress->name ] The Client has been successfully created ($deliveryAddressId).");

            } catch (Exception $exception) {
                writeLog("[ Client delivery address for client : $client->name ] " . $exception->getMessage() . "\n", $exception->getCode(), EVOLIZ_LOG_ERROR);
            }
        }

        return $deliveryAddressId ?? null;
    }
}
