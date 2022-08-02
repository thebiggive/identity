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
    /** Share this to make sure we don't surface the difference between no account + wrong password. */
    public const BAD_LOGIN_MESSAGE = 'Invalid credentials';

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
            throw new AuthenticationException(static::BAD_LOGIN_MESSAGE);
        }
    }
}
