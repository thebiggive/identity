<?php

declare(strict_types=1);

namespace BigGive\Identity\Application\Middleware;

use BigGive\Identity\Domain\Credentials;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpBadRequestException;

class CredentialsCaptchaMiddleware extends CaptchaMiddleware
{
    protected function getCode(ServerRequestInterface $request): ?string
    {
        $body = (string) $request->getBody();

        try {
            $credentials = $this->serializer->deserialize(
                $body,
                Credentials::class,
                'json'
            );
        } catch (\TypeError $e) {
            throw new HttpBadRequestException($request, 'Bad login request format');
        }

        return $credentials->captcha_code;
    }
}
