<?php

namespace BigGive\Identity\Application\Actions\EmailVerificationToken;

use BigGive\Identity\Application\Actions\Action;
use BigGive\Identity\Domain\EmailVerificationToken;
use BigGive\Identity\Repository\EmailVerificationTokenRepository;
use Laminas\Diactoros\Response\JsonResponse;
use OpenApi\Annotations as OA;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpNotFoundException;

/**
 *  @OA\Post(
 *     path="/v1/emailVerificationToken/check-is-valid-no-person-id",
 *     summary="Get an email verification token to check if its valid",
 *     @OA\RequestBody(
 *         description="",
 *         required=true,
 *         @OA\JsonContent(
 *              @OA\Property(property="email_address", type="string", example="fred@example.com"),
 *              @OA\Property(property="secret", type="string", example="123456"),
 *        )
 *     ),
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
 */
class GetEmailVerificationTokenNoPersonId extends Action
{
    public function __construct(
        private \DateTimeImmutable $now,
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
        try {
            $requestBody = json_decode(
                (string)$request->getBody(),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
            \assert(is_array($requestBody));
        } catch (\JsonException $exception) {
            return $this->validationError(
                $exception->getMessage(),
            );
        }

        $emailAddress = (string) ($requestBody["emailAddress"] ?? throw new HttpBadRequestException($request));
        $tokenSecretSupplied = (string) ($requestBody["secret"] ?? throw new HttpBadRequestException($request));

        $oldestAllowedTokenCreationDate = EmailVerificationToken::oldestCreationDateForViewingToken($this->now);

        $token = $this->emailVerificationTokenRepository->findToken(
            email_address: $emailAddress,
            tokenSecret: $tokenSecretSupplied,
            createdSince: $oldestAllowedTokenCreationDate
        );

        if (!$token) {
            throw new HttpNotFoundException($request);
        }

        return new JsonResponse([
            'token' => [
                'valid' => true,
                'email_address' => $emailAddress,
                'first_name' => null,
                'last_name' => null,
            ]
        ]);
    }
}
