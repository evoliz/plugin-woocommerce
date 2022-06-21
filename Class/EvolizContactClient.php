<?php

use Evoliz\Client\Config;
use Evoliz\Client\Exception\ResourceException;
use Evoliz\Client\Model\Clients\ContactClient;
use Evoliz\Client\Repository\Clients\ContactClientRepository;

abstract class EvolizContactClient
{
    /**
     * @param Config $config Configuration for API usage
     * @param object $order Woocommerce order
     * @param int $clientId Linked client identifier
     * @return int|null Contact client identifier
     * @throws ResourceException|Exception
     */
    public static function findOrCreate(Config $config, object $order, int $clientId): ?int
    {
        $contactName = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
        writeLog("[ Contact Client : $contactName ] Search for a match between the Contact Client and the Evoliz database...");

        $contactClientRepository = new ContactClientRepository($config);

        $email = $order->get_billing_email();
        $matchingContactClients = $contactClientRepository->list([
            'clientid' => $clientId,
            'email' => $email
        ]);

        if (!empty($matchingContactClients->data)) {
            foreach ($matchingContactClients->data as $matchingContactClient) {
                $contactId = $matchingContactClient->contactid;
                writeLog("[ Contact Client : $contactName ] Match found with the Evoliz database ($contactId).");
            }
        }

        if (!isset($contactId)) {
            try {
                writeLog("[ Contact Client : $contactName ] No match found. Creating the Contact Client from WooCommerce to Evoliz...");

                $contactClientData = [
                    'clientid' => $clientId,
                    'lastname' => $order->get_billing_last_name(),
                    'firstname' => $order->get_billing_first_name(),
                    'email' => $email
                ];
                $newContactClient = $contactClientRepository->create(new ContactClient($contactClientData));
                $contactId = $newContactClient->contactid;
                writeLog("[ Contact Client : $contactName ] The Contact Client has been successfully created ($contactId).");
            } catch (Exception $exception) {
                writeLog("[ Contact Client : $contactName ] " . $exception->getMessage() . "\n", $exception->getCode(), EVOLIZ_LOG_ERROR);
            }
        }

        return $contactId ?? null;
    }
}
