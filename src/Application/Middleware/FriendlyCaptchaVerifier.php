<?php

namespace BigGive\Identity\Application\Middleware;

use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;

class FriendlyCaptchaVerifier
{
    public function __construct(
        private Client $client,
        private string $secret,
        private string $siteKey,
        private LoggerInterface $logger,
    ) {
    }
    /**
     * @param string $solution Captcha solution submitted from the browser
     *
     * @return bool Whether or not solution is valid.
     * Returns true in case of an error connecting to the Friendly Captcha Server
     */
    public function verify(string $solution): bool
    {
        if (getenv('APP_ENV') === 'regression') {
            return true;
        }

        $response = $this->client->post(
            'https://api.friendlycaptcha.com/api/v1/siteverify',
            [
                'json' => [
                    'solution' => $solution,
                    'secret' => $this->secret,
                    'siteKey' => $this->siteKey,
                ],
                'http_errors' => false, // https://docs.guzzlephp.org/en/stable/request-options.html#http-errors
            ]
        );

        $statusCode = $response->getStatusCode();
        $responseContent = $response->getBody()->getContents();

        if ($statusCode !== 200) {
            // we can log part of the secret for debugging so we can see which one its using without exposing the whole
            // secret.
            $secretEndsWith = substr($this->secret, -3);
            $this->logger->error("Friendly Captcha verification failed: ($statusCode), {$response->getReasonPhrase()}");
            $this->logger->error("Friendly Captcha verification response:" . $responseContent);
            $this->logger->info("Configured friendly captcha secret ends with: $secretEndsWith");
            // not the fault of the client if we don't get a 200 response, so we must assume their solution was good.

            return true;
        }

        $responseData = json_decode($responseContent, true);

        \assert(is_array($responseData));

        return (bool) $responseData['success'];
    }
}
