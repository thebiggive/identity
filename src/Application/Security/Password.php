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
    /**
     * Share this to make sure we don't surface the difference between no account + wrong password.
     * @var string
     */
    public const BAD_LOGIN_MESSAGE = 'Your email or password is incorrect';

    public static function hash(string $rawPassword): string
    {
        return password_hash(password: $rawPassword, algo: PASSWORD_BCRYPT, options: ["cost" => 12]);
    }

    /**
     * @throws AuthenticationException on incorrect password.
     */
    public static function verify(string $rawPassword, Person $person): void
    {
        $hash = $person->getPasswordHash();

        if ($hash === null) {
            throw new AuthenticationException(static::BAD_LOGIN_MESSAGE);
        }

        if (!password_verify($rawPassword, $hash)) {
            throw new AuthenticationException(static::BAD_LOGIN_MESSAGE);
        }
    }
}
