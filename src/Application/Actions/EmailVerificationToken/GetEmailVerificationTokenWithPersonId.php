<?php

namespace BigGive\Identity\Application\Actions\EmailVerificationToken;

use BigGive\Identity\Application\Actions\Action;
use BigGive\Identity\Application\Actions\Person\SetFirstPassword;
use BigGive\Identity\Application\Auth\TokenService;
use BigGive\Identity\Domain\EmailVerificationToken;
use BigGive\Identity\Repository\EmailVerificationTokenRepository;
use BigGive\Identity\Repository\PersonRepository;
use Doctrine\Migrations\Configuration\Migration\JsonFile;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Laminas\Diactoros\Response\JsonResponse;
use OpenApi\Annotations as OA;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpNotFoundException;

/**
 * // phpcs:disable -- OpenAPI needs long lines for good descriptions .
 *  @OA\Get(
 *     path="/v1/emailVerificationToken/{secret}/{personId}",
 *     summary="Get an email verification token to check if its valid for an existng user account that does not yet have a password",
 *     @OA\PathParameter(
 *        name="personId",
 *        description="UUID of the person",
 *        @OA\Schema(
 *        type="string",
 *        format="uuid",
 *        example="f7095caf-7180-4ddf-a212-44bacde69066",
 *        pattern="[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}",
 *        ),
 *     ),
 *      @OA\PathParameter(
 *      name="secret",
 *      description="secret code sent by email",
 *      @OA\Schema(
 *      type="string",
 *      example="123456",
 *      ),
*      ),
 *     @OA\Response(
 *         response=200,
 *         description="Token created and emailed to user, if they are NOT already registered exist, which the server
 *          does not confirm or deny via http. If they are already registered then they are sent a message to remind
 *          them of that.",
 *         @OA\JsonContent(
 *          @OA\Property(property="token", type="object", example={
 *              "valid": true,
 *              "email_address": "email@example.com",
 *              "first_name": "Joe",
 *              "last_name": "Bloggs",
 *              }
 *          ),
 *      ),
 *        ),
 *     @OA\Response(
 *         response=404,
 *         description="Token never existed, is expired, or has been used to create an account.",
 *         @OA\JsonContent(),
 *        )
 *     )
 *   )
 * )
 * // phpcs:enable
 */
class GetEmailVerificationTokenWithPersonId extends Action
{
    public function __construct(
        private \DateTimeImmutable $now,
        private PersonRepository $personRepository,
        private EmailVerificationTokenRepository $emailVerificationTokenRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    /**
     * Given that the user knows (i.e. has a link that encodes) their secret token number
     * and their Person UUID, then they should be able to get a response that tells them:
     *  Whether the token is still valid (e.g. not expired), and if so:
     *  The Email address, first name, and last name on the associated account.
     */
    protected function action(Request $request, array $args): Response
    {
        $tokenSecretSupplied = $this->resolveArg($args, $request, 'secret');
        if (! is_string($tokenSecretSupplied)) {
            throw new HttpNotFoundException($request);
        }

        $person = $this->personRepository->find($this->resolveArg($args, $request, 'personId'));
        $email_address = $person?->email_address;

        if (!$person || $email_address === null) {
            throw new HttpNotFoundException($request);
        }

        if ($person->raw_password !== null) {
            throw new HttpNotFoundException($request);
        }

        /**
         * Only allow viewing up to 5 minutes before the token is expired as far as {@see SetFirstPassword} is
         * concerned, so user has time to fill in the registration form.
         *
         * Very few users will be trying to user the token exactly around the expiry time.
         */
        $oldestAllowedTokenCreationDate = $this->now
            ->sub(new \DateInterval('PT' . TokenService::COMPLETE_ACCOUNT_VALIDITY_PERIOD_SECONDS . 'S'))
            ->modify('+5 minutes');

        $token = $this->emailVerificationTokenRepository->findToken(
            email_address: $email_address,
            tokenSecret: $tokenSecretSupplied,
            createdSince: $oldestAllowedTokenCreationDate
        );

        if (!$token) {
            throw new HttpNotFoundException($request);
        }

        return new JsonResponse([
            'token' => [
                'valid' => true,
                'email_address' => $email_address,
                'first_name' => $person->first_name,
                'last_name' => $person->last_name,
            ]
        ]);
    }
}
