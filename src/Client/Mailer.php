<?php

namespace BigGive\Identity\Client;

use BigGive\Identity\Application\Settings\SettingsInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;

class Mailer
{
    public function __construct(
        private readonly SettingsInterface $settings,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function sendEmail(array $requestBody): bool
    {
        try {
            $httpClient = new Client([
                'timeout' => $this->settings->get('apiClient')['global']['timeout'],
            ]);

            $response = $httpClient->post(
                $this->settings->get('apiClient')['mailer']['baseUri'] . '/v1/send',
                [
                    'json' => $requestBody,
                    'headers' => [
                        'x-send-verify-hash' => $this->hash(json_encode($requestBody)),
                    ],
                ]
            );

            return $response->getStatusCode() == 200;
        } catch (GuzzleException | RequestException $ex) {
            $this->logger->error(sprintf(
                'Donor registration email exception %s with error code %s: %s. Body: %s',
                get_class($ex),
                $ex->getCode(),
                $ex->getMessage(),
                $ex->getResponse() ? $ex->getResponse()->getBody() : 'N/A',
            ));
            return false;
        }
    }

    private function hash(string $body): string
    {
        return hash_hmac('sha256', trim($body), $this->settings->get('apiClient')['mailer']['sendSecret']);
    }
}
