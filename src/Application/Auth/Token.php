<?php

declare(strict_types=1);

namespace BigGive\Identity\Application\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Psr\Log\LoggerInterface;

class Token
{
    /**
     * @link https://stackoverflow.com/questions/39239051/rs256-vs-hs256-whats-the-difference has info on hash
     * algorithm choice. Since we use the secret only server-side and will secure it like other secrets,
     * and symmetric is faster, it's the best and simplest fit for this use case.
     */
    private static string $algorithm = 'HS256';

    /**
     * @param string $personId UUID for a person
     * @return string Signed JWS
     */
    public static function create(string $personId): string
    {
        /**
         * @var array $claims
         * @link https://tools.ietf.org/html/rfc7519 has info on the standard keys like `exp`
         */
        $claims = [
            'iss' => getenv('BASE_URI'),
            'iat' => time(),
            'exp' => time() + (15 * 86400), // Expire in 15 days
            'sub' => [
                'person_id' => $personId,
            ],
        ];

        return JWT::encode($claims, static::getSecret(), static::$algorithm);
    }

    /**
     * @param string            $personId   UUID for a person
     * @param string            $jws        Compact JWS (signed JWT)
     * @param LoggerInterface   $logger
     * @return bool Whether the token is valid for the given person.
     */
    public static function check(string $personId, string $jws, LoggerInterface $logger): bool
    {
        $key = new Key(static::getSecret(), static::$algorithm);
        try {
            $decodedJwtBody = JWT::decode($jws, $key);
        } catch (\Exception $exception) {
            $type = get_class($exception);
            // This is only a warning for now. We've seen likely crawlers + bots send invalid
            // requests. In the event that we find they are sending partial JWTs (rather than
            // none) and so getting here we might consider further reducing this log to `info()`
            // level so we can spot more serious issues.
            $logger->warning("JWT error: decoding for person ID $personId: $type - {$exception->getMessage()}");

            return false;
        }

        if ($decodedJwtBody->iss !== getenv('BASE_URI')) {
            $logger->error("JWT error: issued by wrong site {$decodedJwtBody->iss}");

            return false;
        }

        if ($personId !== $decodedJwtBody->sub->person_id) {
            $logger->error("JWT error: Not authorised for person ID $personId");

            return false;
        }

        return true;
    }

    private static function getSecret(): string
    {
        return getenv('JWT_ID_SECRET');
    }
}
