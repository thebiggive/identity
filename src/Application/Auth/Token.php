<?php

declare(strict_types=1);

namespace BigGive\Identity\Application\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Psr\Log\LoggerInterface;

class Token
{
    /**
     * Time for which we allow a person with password to stay logged in, also used as the time limit for a new
     * donor to set their first password via the link in the donation thanks email.
     */
    public const int COMPLETE_ACCOUNT_VALIDITY_PERIOD_SECONDS = 8 * 60 * 60;

    /**
     * Time for a session to continue for a guest donor when they don't set a password.
     */
    public const int GUEST_ACCOUNT_VALIDITY_PERIOD_SECONDS = 1 * 60 * 60;

    /**
     * @link https://stackoverflow.com/questions/39239051/rs256-vs-hs256-whats-the-difference has info on hash
     * algorithm choice. Since we use the secret only server-side and will secure it like other secrets,
     * and symmetric is faster, it's the best and simplest fit for this use case.
     */
    private static string $algorithm = 'HS256';

    /**
     * @param string    $personId   UUID for a person
     * @param bool      $complete   Whether the token is for a login-ready Person. If so, it should be
     *                              issued only after password authentication. When this is false, it's
     *                              for a Person who has just been created and is designed for reading
     *                              & setting basic details and optionally an initial password.
     * @return string Signed JWS
     */
    public static function create(
        \DateTimeImmutable $startingAt,
        string $personId,
        bool $complete,
        ?string $pspCustomerId
    ): string {
        $personClaims = [
            'person_id' => $personId,
            'complete' => $complete,
        ];

        if ($pspCustomerId !== null) {
            $personClaims['psp_id'] = $pspCustomerId;
        }

        $startTimestamp = $startingAt->getTimestamp();

        if ($complete) {
            $expiryTimestamp = $startTimestamp + self::COMPLETE_ACCOUNT_VALIDITY_PERIOD_SECONDS;
        } else {
            $expiryTimestamp = $startTimestamp + self::GUEST_ACCOUNT_VALIDITY_PERIOD_SECONDS;
        }

        /**
         * @var array $claims
         * @link https://tools.ietf.org/html/rfc7519 has info on the standard keys like `exp`
         */
        $claims = [
            'iss' => getenv('BASE_URI'),
            'iat' => $startTimestamp,
            'exp' => $expiryTimestamp,
            'sub' => $personClaims,
        ];

        return JWT::encode($claims, static::getSecret(), static::$algorithm);
    }

    /**
     * @param string            $personId   UUID for a person
     * @param ?bool             $complete   Whether the token is meant to be for a login-ready Person.
     *                                      `null` if either is acceptable.
     * @param string            $jws        Compact JWS (signed JWT)
     * @param LoggerInterface   $logger
     * @return bool Whether the token is valid for the given person.
     */
    public static function check(string $personId, ?bool $complete, string $jws, LoggerInterface $logger): bool
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
            $logger->warning("JWT error: Not authorised for person ID $personId");

            return false;
        }

        if ($complete !== null && $complete !== $decodedJwtBody->sub->complete) {
            $logger->warning(
                sprintf("JWT error: Not authorised for %s status", $complete ? 'complete' : 'incomplete'),
            );

            return false;
        }

        return true;
    }

    private static function getSecret(): string
    {
        $secret = getenv('JWT_ID_SECRET');

        if ($secret === false) {
            throw new \Exception("JWT_ID_SECRET not set in environment");
        }

        return $secret;
    }
}
