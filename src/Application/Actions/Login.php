<?php

declare(strict_types=1);

namespace BigGive\Identity\Application\Actions;

use BigGive\Identity\Application\Auth\Token;
use BigGive\Identity\Application\Security\AuthenticationException;
use BigGive\Identity\Application\Security\Password;
use BigGive\Identity\Domain\Credentials;
use BigGive\Identity\Repository\PersonRepository;
use Laminas\Diactoros\Response\JsonResponse;
use OpenApi\Annotations as OA;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpBadRequestException;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use TypeError;

/**
 * @OA\Post(
 *     path="/v1/auth",
 *     summary="Log in to get a token for authenticated Identity and MatchBot calls",
 *     operationId="authenticate",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(ref="#/components/schemas/Credentials"),
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Authenticated",
 *         @OA\JsonContent(
 *          format="object",
 *          example={
 *              "jwt": "some.token.123",
 *          },
 *         ),
 *     ),
 *     @OA\Response(
 *         response=400,
 *         description="Invalid or missing data",
 *         @OA\JsonContent(
 *          format="object",
 *          example={
 *              "error": {
 *                  "description": "The error details",
 *              },
 *          },
 *         ),
 *     ),
 *     @OA\Response(
 *         response=401,
 *         description="Authentication failed",
 *     ),
 * )
 */
class Login extends Action
{
    public function __construct(
        LoggerInterface $logger,
        private readonly PersonRepository $personRepository,
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator,
    ) {
        parent::__construct($logger);
    }

    /**
     * @return Response
     * @throws HttpBadRequestException
     */
    protected function action(): Response
    {
        try {
            /** @var Credentials $credentials */
            $credentials = $this->serializer->deserialize(
                $body = ((string) $this->request->getBody()),
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

        $person = $this->personRepository->findPersonByEmailAddress($credentials->email_address);
        if (!$person) {
            return $this->fail(Password::BAD_LOGIN_MESSAGE);
        }

        // Throws on bad password.
        try {
            Password::verify($credentials->raw_password, $person);
        } catch (AuthenticationException $exception) {
            return $this->fail($exception->getMessage());
        }

        $id = (string) $person->getId();

        return new JsonResponse([
            'id' => $id,
            'jwt' => Token::create($id, true, $person->stripe_customer_id),
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
