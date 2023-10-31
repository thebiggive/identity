<?php

declare(strict_types=1);

namespace BigGive\Identity\Application\Security;

use BigGive\Identity\Domain\Person;

class Password
{
    /** @var string */
    private const ALGORITHM = PASSWORD_BCRYPT;
    /** @var array<array-key, mixed> */
    private const OPTIONS = ['cost' => 12];

    /**
     * Share this to make sure we don't surface the difference between no account + wrong password.
     * @var string
     */
    public const BAD_LOGIN_MESSAGE = 'Your email or password is incorrect';

    public static function hash(string $rawPassword): string
    {
        return password_hash(password: $rawPassword, algo: self::ALGORITHM, options: self::OPTIONS);
    }

    public static function needsRehash(string $hash): bool
    {
        return password_needs_rehash(hash: $hash, algo: self::ALGORITHM, options: self::OPTIONS);
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
