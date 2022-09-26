<?php

namespace BigGive\Identity\Client;

use BigGive\Identity\Application\Settings\SettingsInterface;
use BigGive\Identity\Domain\Person;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;

class Mailer
{
    public function __construct(
        private readonly ClientInterface $client,
        private readonly SettingsInterface $settings,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function sendEmail(array $requestBody): bool
    {
        try {
            $response = $this->client->post(
                $this->settings->get('apiClient')['mailer']['baseUri'] . '/v1/send',
                [
                    'json' => $requestBody,
                    'headers' => [
                        'x-send-verify-hash' => $this->hash(json_encode($requestBody)),
                    ],
                ]
            );

            if ($response->getStatusCode() === 200) {
                return true;
            } else {
                $this->logger->warning(sprintf(
                    '%s email callout didn\'t return 200. It returned code: %s. Request body: %s. Response body: %s.',
                    $requestBody['templateKey'],
                    $response->getStatusCode(),
                    json_encode($requestBody),
                    $response->getBody(),
                ));
                return false;
            }
        } catch (GuzzleException | RequestException $ex) {
            $this->logger->error(sprintf(
                '%s email exception %s with error code %s: %s. Body: %s',
                $requestBody['templateKey'],
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
