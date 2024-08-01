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
    )
    {
    }
    /**
     * @param string $solution Captcha solution submitted from the browser
     *
     * @return bool Whether or not solution is valid.
     * Returns true in case of an error connecting to the Friendly Captcha Server
     */
    public function verify(string $solution): bool
    {
        $response = $this->client->post(
            'https://api.friendlycaptcha.com/api/v1/siteverify',
            [
                'body' => \json_encode([
                    'solution' => $solution,
                    'secret' => $this->secret,
                    'siteKey' => $this->siteKey,
                ])
            ]
        );

        $statusCode = $response->getStatusCode();
        if ($statusCode  !== 200) {
            $this->logger->error("Friendly Captcha verification failed: ($statusCode), {$response->getReasonPhrase()}");
            // not the fault of the client if we don't get a 200 response, so we must assume their solution was good.
            return true;
        }

        $responseData = json_decode($response->getBody()->getContents(), true);
        \assert(is_array($responseData));

        return (bool) $responseData['success'];
    }
}
