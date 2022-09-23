<?php

declare(strict_types=1);

namespace BigGive\Identity\Application\Middleware;

use BigGive\Identity\Domain\Credentials;
use Psr\Http\Message\ServerRequestInterface;

class CredentialsRecaptchaMiddleware extends RecaptchaMiddleware
{
    protected function getCode(ServerRequestInterface $request): string
    {
        $body = (string) $request->getBody();

        /** @var Credentials $credentials */
        $credentials = $this->serializer->deserialize(
            $body,
            Credentials::class,
            'json'
        );

        return $credentials->captcha_code;
    }
}
