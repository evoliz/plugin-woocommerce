<?php

use Evoliz\Client\Config;
use Evoliz\Client\Exception\ResourceException;
use Evoliz\Client\Model\Clients\Client\Client;
use Evoliz\Client\Repository\Clients\ClientRepository;

abstract class EvolizClient
{
    /**
     * @param Config $config Configuration for API usage
     * @param object $order Woocommerce order
     * @return int|null Client identifier
     * @throws ResourceException|Exception
     */
    public static function findOrCreate(Config $config, object $order): ?int
    {
        $company = $order->get_billing_company();
        $clientName = isset($company) && $company !== '' ? $company : $order->get_billing_last_name();

        writeLog("[ Client : $clientName ] Search for a match between the Client and the Evoliz database...");

        $clientRepository = new ClientRepository($config);

        $matchingClients = $clientRepository->list(['search' => $clientName]);

        if (!empty($matchingClients->data)) {
            foreach ($matchingClients->data as $matchingClient) {
                if ($matchingClient->address->postcode === $order->get_billing_postcode()
                    && $matchingClient->address->town === $order->get_billing_city()
                    && $matchingClient->address->country->iso2 === $order->get_billing_country() // @Todo : Sur quoi se baser ? Les required ?
                ) {
                    $clientId = $matchingClient->clientid;
                    writeLog("[ Client : $clientName ] Match found with the Evoliz database ($clientId).");
                }
            }
        }

        if (!isset($clientId)) {
            try {
                writeLog("[ Client : $clientName ] No match found. Creating the Client from WooCommerce to Evoliz...");

                $clientData = [
                    'name' => $clientName,
                    'address' => [
                        'postcode' => $order->get_billing_postcode(),
                        'town' => $order->get_billing_city(),
                        'iso2' => $order->get_billing_country() ?? 'FR',
                        'addr' => $order->get_billing_address_1()
                    ],
                    'phone' => (string) $order->get_billing_phone(),
                    'comment' => 'Client created from Woocommerce'
                ];

                if (get_option('wc_evz_enable_vat_number') === 'on') {
                    if (get_option('wc_evz_eu_vat_number') !== null && get_option('wc_evz_eu_vat_number') !== '') {
                        foreach ($order->get_meta_data() as $metaData) {
                            if ($metaData->key === get_option('wc_evz_eu_vat_number')) {
                                $vatNumber = $metaData->value;
                            }
                        }
                    } else {
                        foreach ($order->get_meta_data() as $metaData) {
                            if ($metaData->key === 'vat_number') {
                                $vatNumber = $metaData->value;
                            }
                        }
                    }
                }

                if (isset($company) && $company !== '') {
                    $clientData['type'] = 'Professionnel';
                    $clientData['vat_number'] = isset($vatNumber) && $vatNumber !== '' ? $vatNumber : 'N/C';
                } else {
                    $clientData['type'] = 'Particulier';
                }

                if ($order->get_billing_address_2() !== null && $order->get_billing_address_2() !== '') {
                    $clientData['address']['addr2'] = $order->get_billing_address_2();
                }

                if ($order->get_shipping_postcode() !== null && $order->get_shipping_postcode() !== '') {
                    $clientData['delivery_address'] = array_filter([
                        "postcode" => $order->get_shipping_postcode(),
                        'town' => $order->get_shipping_city(),
                        'iso2' => $order->get_shipping_country(),
                        'addr' => $order->get_shipping_address_1(),
                        'addr2' => $order->get_shipping_address_2()
                    ], function ($value) {
                        return isset($value) && $value !== '';
                    });
                }

                $newClient = $clientRepository->create(new Client($clientData));
                $clientId = $newClient->clientid;
                writeLog("[ Client : $clientName ] The Client has been successfully created ($clientId).");

            } catch (Exception $exception) {
                writeLog("[ Client : $clientName ] " . $exception->getMessage() . "\n", $exception->getCode(), EVOLIZ_LOG_ERROR);
            }
        }

        return $clientId ?? null;
    }

    /**
     * @param Config $config Configuration for API usage
     * @param int $clientId Client identifier
     * @return bool
     * @throws ResourceException|Exception
     */
    public static function isProfessional(Config $config, int $clientId): bool
    {
        $clientRepository = new ClientRepository($config);
        $client = $clientRepository->detail($clientId);

        return $client->type === 'Professionnel';
    }
}
