<?php

use Evoliz\Client\Config;
use Evoliz\Client\Exception\ResourceException;
use Evoliz\Client\Model\Clients\Client\Client;
use Evoliz\Client\Repository\Clients\ClientRepository;
use Evoliz\Client\Response\APIResponse;

abstract class EvolizClient
{
    /**
     * @throws ResourceException|Exception
     */
    public static function findOrCreate(Config $config, object $order): ?Client
    {
        $client = null;
        $company = $order->get_billing_company();
        $clientName = isset($company) && $company !== '' ? $company : $order->get_billing_last_name();

        writeLog("[ Client : $clientName ] Search for a match between the Client and the Evoliz database...");

        $clientRepository = new ClientRepository($config);

        $matchingClients = $clientRepository->list([
            'search' => $clientName,
            'enabled' => true,
        ]);

        if (!empty($matchingClients->data)) {
            foreach ($matchingClients->data as $matchingClient) {
                if ($matchingClient->address->postcode === $order->get_billing_postcode()
                    && $matchingClient->address->town === $order->get_billing_city()
                    && $matchingClient->address->country->iso2 === $order->get_billing_country() // @Todo : Sur quoi se baser ? Les required ?
                ) {
                    $clientId = $matchingClient->clientid;
                    writeLog("[ Client : $clientName ] Match found with the Evoliz database ($clientId).");
                    $client = new Client((array) $matchingClient);
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
                        'addr' => $order->get_billing_address_1() ?? ''
                    ],
                    'comment' => 'Client created from Woocommerce'
                ];

                if (!empty($order->get_billing_phone())) {
                    $clientData['phone'] = $order->get_billing_phone();
                }

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

                $client = $clientRepository->create(new Client($clientData))->createFromResponse();
                writeLog("[ Client : $clientName ] The Client has been successfully created ($client->clientid).");

            } catch (Exception $exception) {

                writeLog("[ Client : $clientName ] " . $exception->getMessage() . "\n", $exception->getCode(), EVOLIZ_LOG_ERROR);
            }
        }

        return $client ?? null;
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
