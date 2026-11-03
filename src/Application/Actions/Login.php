<?php

declare(strict_types=1);

namespace BigGive\Identity\Application\Actions;

use BigGive\Identity\Application\Auth\TokenService;
use BigGive\Identity\Application\Security\AuthenticationException;
use BigGive\Identity\Application\Security\Password;
use BigGive\Identity\Domain\Credentials;
use BigGive\Identity\Repository\PersonRepository;
use Laminas\Diactoros\Response\JsonResponse;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpBadRequestException;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use TypeError;

#[OA\Post(
    path: '/v1/auth',
    summary: 'Log in to get a token for authenticated Identity and MatchBot calls',
    operationId: 'authenticate',
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(ref: '#/components/schemas/Credentials'),
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Authenticated',
            content: new OA\JsonContent(format: 'object', example: ['jwt' => 'some.token.123']),
        ),
        new OA\Response(
            response: 400,
            description: 'Invalid or missing data',
            content: new OA\JsonContent(format: 'object', example: ['error' => ['description' => 'The error details']]),
        ),
        new OA\Response(response: 401, description: 'Authentication failed'),
    ],
)]
class Login extends Action
{
    public function __construct(
        LoggerInterface $logger,
        private readonly PersonRepository $personRepository,
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator,
        private readonly TokenService $tokenService,
        private readonly \DateTimeImmutable $now,
    ) {
        parent::__construct($logger);
    }

    /**
     * @param array $args
     * @return Response
     * @throws HttpBadRequestException
     */
    protected function action(Request $request, array $args): Response
    {
        $body = ((string) $request->getBody());
        try {
            $credentials = $this->serializer->deserialize(
                $body,
                Credentials::class,
                'json',
            );
        } catch (UnexpectedValueException | TypeError $exception) {
            // UnexpectedValueException is the Serializer one, not the global one
            $this->logger->info(sprintf('%s non-serialisable payload was: %s', __CLASS__, $body));

            $message = 'Login data deserialise error';
            $exceptionType = get_class($exception);

            return $this->validationError(
                "$message: $exceptionType - {$exception->getMessage()}",
                $message,
                empty($body), // Suspected bot / junk traffic sometimes sends blank payload.
            );
        }

        $violations = $this->validator->validate($credentials);

        if (count($violations) > 0) {
            $message = 'Validation error: ';

            $violationDetails = [];
            foreach ($violations as $violation) {
                $violationDetails[] = $this->summariseConstraintViolation($violation);
            }

            $message .= implode('; ', $violationDetails);

            return $this->validationError(
                $message,
                null,
                true,
            );
        }

        $person = $this->personRepository->findPasswordEnabledPersonByEmailAddress($credentials->email_address);
        if (!$person) {
            return $this->fail(Password::BAD_LOGIN_MESSAGE);
        }

        $HTTP_X_FORWARDED_FOR = $_SERVER[Action::FORWARDED_IP_HEADER_ARRAY_KEY] ?? null;
        $clientIp = $HTTP_X_FORWARDED_FOR !== null ? \trim(\explode(',', $HTTP_X_FORWARDED_FOR)[0]) : null;

        if ($person->isLockedOutForIp($clientIp, $this->now)) {
            return $this->fail(Password::ACCOUNT_LOCKED_MESSAGE);
        }

        // Throws on bad password.
        try {
            Password::verify($credentials->raw_password, $person);
        } catch (AuthenticationException $exception) {
            $person->recordFailedLogin($this->now);
            $this->personRepository->persist($person, true);

            return $this->fail($exception->getMessage());
        }

        $person->recordSuccessfulLogin($clientIp, $this->now);
        $this->personRepository->persist($person, true);

        $this->personRepository->upgradePasswordIfPossible($credentials->raw_password, $person);

        $id = (string) $person->getId();

        $this->logger->info(sprintf(
            'Login success: UUID %s, Stripe Customer %s',
            $id,
            $person->stripe_customer_id ?? '[none]',
        ));

        return new JsonResponse([
            'id' => $id,
            'jwt' => $this->tokenService->create(new \DateTimeImmutable(), $id, true, $person->stripe_customer_id),
        ]);
    }

    private function fail(string $message): Response
    {
        return $this->validationError(
            $message,
            null,
            true,
            401
        );
    }
}
