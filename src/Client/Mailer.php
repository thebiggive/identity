<?php

namespace BigGive\Identity\Client;

use BigGive\Identity\Application\Settings\SettingsInterface;
use GuzzleHttp\Client;

class Mailer
{

    public function __construct(
        private readonly SettingsInterface $settings,
    )
    {
    }

    public function sendEmail(array $requestBody) {
        $this->httpClient = new Client([
            'timeout' => $this->settings->get('apiClient')['global']['timeout'],
        ]);

        $this->httpClient->post(
            $this->settings->get('apiClient')['mailer']['baseUri'] . '/v1/send',
            [
                'json' => $requestBody,
                'headers' => [
                    'x-send-verify-hash' => $this->hash(json_encode($requestBody)),
                ],
            ]
        );
    }

    private function hash(string $body): string
    {
        return hash_hmac('sha256', trim($body), $this->settings->get('apiClient')['mailer']['sendSecret']);
    }
}