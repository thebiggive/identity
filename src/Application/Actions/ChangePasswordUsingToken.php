<?php

namespace BigGive\Identity\Application\Actions;

use BigGive\Identity\Domain\Person;
use BigGive\Identity\Repository\PasswordResetTokenRepository;
use BigGive\Identity\Repository\PersonRepository;
use Laminas\Diactoros\Response\JsonResponse;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpBadRequestException;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @link https://stripe.com/docs/payments/customer-balance/funding-instructions?bt-region-tabs=uk
 */
#[OA\Post(
    path: '/v1/change-forgotten-password',
    summary: 'Change a forgotten password using a password reset token',
    operationId: 'password_reset_complete',
    requestBody: new OA\RequestBody(
        description: '',
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'secret', type: 'string', example: 'EivZrmdxk4YJXQC37Q6Cnu'),
                new OA\Property(property: 'new_password', type: 'string', example: 'Open sesame'),
            ],
        ),
    ),
    responses: [
        new OA\Response(response: 200, description: 'Password changed', content: new OA\JsonContent()),
        new OA\Response(
            response: 400,
            description: 'Returned if the new password is bad (e.g. too short), ' .
                'or if the secret token is invalid or expired',
            content: new OA\JsonContent(),
        ),
    ],
)]
class ChangePasswordUsingToken extends Action
{
    public function __construct(
        LoggerInterface $logger,
        private readonly PersonRepository $personRepository,
        private readonly PasswordResetTokenRepository $tokenRepository,
        private readonly ValidatorInterface $validator,
        private readonly \DateTimeImmutable $now,
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
        $violations = $this->validator->validate($person, null, [Person::VALIDATION_COMPLETE]);

        $relevantViolationMessages = [];

        foreach ($violations as $violation) {
            if (
                $violation->getPropertyPath() === 'raw_password' ||
                str_contains((string) $violation->getMessage(), 'password')
            ) {
                $relevantViolationMessages[] = $this->summariseConstraintViolationAsHtmlSnippet($violation);
            }
        }

        if ($relevantViolationMessages !== []) {
            throw new HttpBadRequestException($request, implode("; ", $relevantViolationMessages));
        }

        $token->consume(new \DateTimeImmutable());
        $person->email_address_verified = $this->now;
        $this->personRepository->persistForPasswordChange($person);
        $this->tokenRepository->persist($token);

        return new JsonResponse([]);
    }
}
