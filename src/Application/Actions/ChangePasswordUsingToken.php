<?php

namespace BigGive\Identity\Application\Actions;

use BigGive\Identity\Client\Mailer;
use BigGive\Identity\Domain\PasswordResetToken;
use BigGive\Identity\Repository\PasswordResetTokenRepository;
use BigGive\Identity\Repository\PersonRepository;
use Laminas\Diactoros\Response\JsonResponse;
use OpenApi\Annotations as OA;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpBadRequestException;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @OA\Post(
 *     path="/v1/change-forgotten-password",
 *     summary="Change a forgotten password using a password reset token",
 *     @OA\RequestBody(
 *         description="",
 *         required=true,
 *         @OA\JsonContent(
 *              @OA\Property(property="secret", type="string", example="EivZrmdxk4YJXQC37Q6Cnu"),
 *              @OA\Property(property="new_password", type="string", example="Open sesame"),
 *   )
 * ),
 *     @OA\Response(
 *         response=200,
 *         description="Password changed",
 *         @OA\JsonContent(),
 *     ),
*      @OA\Response(
*         response=400,
*         description="Returned if the new password is bad (e.g. too short), or it the secret token is invalid or expired",
*         @OA\JsonContent(),
*     ),
 * ),
 * @link https://stripe.com/docs/payments/customer-balance/funding-instructions?bt-region-tabs=uk
 */
class ChangePasswordUsingToken extends Action
{
    public function __construct(
        LoggerInterface $logger,
        private readonly PersonRepository $personRepository,
        private readonly PasswordResetTokenRepository $tokenRepository,
        private readonly ValidatorInterface $validator,
        private readonly Mailer $mailer,
    ) {
        parent::__construct($logger);
    }

    protected function action(Request $request, array $args): Response
    {
        /** @var array $requestData */
        $requestData = json_decode((string) $request->getBody(), true);

        $secret = (string) ($requestData['secret'] ?? throw new \Exception('Missing secret'));

        $secretUuid = Uuid::fromBase58($secret);

        $token = $this->tokenRepository->findForUse($secretUuid);

        if ($token === null) {
            throw new HttpBadRequestException($request, 'Token not found or not valid');
        }

        $person = $token->person;
        $person->raw_password = (string) $requestData['new_password'];
        $violations = $this->validator->validate($person, null, ['complete']);

        $relevantViolationMessages = [];

        foreach ($violations as $violation) {
            if (
                $violation->getPropertyPath() === 'raw_password' ||
                str_contains((string) $violation->getMessage(), 'password')
            ) {
                $relevantViolationMessages[] = $violation->getMessage();
            }
        }

        if ($relevantViolationMessages !== []) {
            throw new HttpBadRequestException($request, implode("; ", $relevantViolationMessages));
        }

        $token->consume(new \DateTimeImmutable());
        $this->personRepository->persistForPasswordChange($person);
        $this->tokenRepository->persist($token);

        return new JsonResponse([]);
    }
}
