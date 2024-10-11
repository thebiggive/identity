<?php

declare(strict_types=1);

namespace BigGive\Identity\Application\Middleware;

use BigGive\Identity\Domain\Person;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;

class PersonCaptchaMiddleware extends CaptchaMiddleware
{
    protected function getCode(ServerRequestInterface $request): ?string
    {
        $body = (string) $request->getBody();

        $captchaCode = '';

        /** @var Person $person */
        try {
            $person = $this->serializer->deserialize(
                $body,
                Person::class,
                'json'
            );
            $captchaCode = $person->captcha_code ?? null;
        } catch (UnexpectedValueException $exception) { // This is the Serializer one, not the global one
            // No-op. Allow verification with blank string to occur. This will fail with the live
            // service, but can be mocked with success in unit tests so we can test handling of other
            // code that might need to handle deserialise errors.
        }

        return $captchaCode;
    }
}
