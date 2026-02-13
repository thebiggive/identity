<?php

namespace BigGive\Identity\Client;

use BigGive\Identity\Application\Settings\SettingsInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;

/**
 * @psalm-type RequestBody = array{templateKey: string, recipientEmailAddress: string, params: array, ...}
 * /
 */
class Mailer
{
    public function __construct(
        private readonly Client $client,
        private readonly SettingsInterface $settings,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @psalm-param RequestBody $requestBody
     */
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
        } catch (RequestException $ex) {
            $this->logger->error(sprintf(
                '%s email exception %s with error code %s: %s. Body: %s',
                $requestBody['templateKey'],
                get_class($ex),
                $ex->getCode(),
                $ex->getMessage(),
                $ex->getResponse() ? $ex->getResponse()->getBody() : 'N/A',
            ));
            return false;
        } catch (GuzzleException $ex) {
            $this->logger->error(sprintf(
                '%s email exception %s with error code %s: %s. Body: %s',
                $requestBody['templateKey'],
                get_class($ex),
                $ex->getCode(),
                $ex->getMessage(),
                'N/A',
            ));
            return false;
        }
    }

    private function hash(string $body): string
    {
        return hash_hmac('sha256', trim($body), $this->settings->get('apiClient')['mailer']['sendSecret']);
    }
}
