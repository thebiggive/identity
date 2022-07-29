<?php

declare(strict_types=1);

namespace BigGive\Identity\Application\Security;

use BigGive\Identity\Domain\Person;

/**
 * @todo support auto rehash when we have an Auth endpoint, so we can continually upgrade password
 * hashes.
 */
class Password
{
    public static function hash(string $rawPassword): string
    {
        return password_hash($rawPassword, PASSWORD_DEFAULT);
    }

    /**
     * @throws AuthenticationException on incorrect password.
     */
    public static function verify(string $rawPassword, Person $person): void
    {
        if (!password_verify($rawPassword, $person->getPasswordHash())) {
            throw new AuthenticationException('Invalid credentials');
        }
    }
}
