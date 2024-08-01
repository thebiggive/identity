<?php

namespace BigGive\Identity\Application\Middleware;

use GuzzleHttp\Client;

class FriendlyCaptchaVerifier
{
    public function __construct(private Client $client, private string $secret, private string $siteKey)
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

        if ($response->getStatusCode() !== 200) {
            // not the fault of the client if we don't get a 200 response, so we must assume their solution was good.
            return true;
        }

        $responseData = json_decode($response->getBody()->getContents(), true);
        \assert(is_array($responseData));

        return (bool) $responseData['success'];
    }
}
