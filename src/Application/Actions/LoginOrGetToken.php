<?php

namespace BigGive\Identity\Application\Actions;

use BigGive\Identity\Application\Auth\TokenService;
use BigGive\Identity\Application\Security\AuthenticationException;
use BigGive\Identity\Application\Security\Password;
use BigGive\Identity\Domain\Credentials;
use BigGive\Identity\Domain\EmailVerificationToken;
use BigGive\Identity\Repository\EmailVerificationTokenRepository;
use BigGive\Identity\Repository\PersonRepository;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpBadRequestException;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Combines the functionality of GetEmailVerificationTokenNoPersonId and Login
 * i.e. takes a donor email address and a secret, and then will try a few optiosn for what the donor might want:
 *
 * - Attempt to log in with the email and password. Return a JWT if successful.
 *
 * - If not successful, check whether there is a currently valid email verification token for that email address and the
 *      secret taken as a token secret instead of a password. If so return the token details so the client knows it can
 *      use them to create an account
 *
 * - Otherwise return an HTTP 401 failure message.
 */
class LoginOrGetToken extends Action
{
    public function __construct(
        private readonly \DateTimeImmutable $now,
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator,
        private readonly TokenService $tokenService,
        LoggerInterface $logger,
        private readonly PersonRepository $personRepository,
        private EmailVerificationTokenRepository $emailVerificationTokenRepository,
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
        // code below copied from Login.php so @todo-DON-1195 consider abstracting into e.g. a service
        // class

        $body = ((string) $request->getBody());
        try {
            /** @var Credentials $credentials */
            $credentials = $this->serializer->deserialize(
                $body,
                Credentials::class,
                'json',
            );
        } catch (UnexpectedValueException | \TypeError $exception) {
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
        if ($person) {
            try {
                // Throws on bad password.
                Password::verify($credentials->raw_password, $person);
            } catch (AuthenticationException) {
                return $this->fail(Password::BAD_LOGIN_MESSAGE);
            }

            $id = (string) $person->getId();
            $this->personRepository->upgradePasswordIfPossible($credentials->raw_password, $person);

            return new JsonResponse([
                'id' => $id,
                'jwt' => $this->tokenService->create(new \DateTimeImmutable(), $id, true, $person->stripe_customer_id),
            ]);
        }

        $oldestAllowedTokenCreationDate = EmailVerificationToken::oldestCreationDateForViewingToken($this->now);

        $token = $this->emailVerificationTokenRepository->findToken(
            email_address: $credentials->email_address,
            tokenSecret: $credentials->raw_password,
            createdSince: $oldestAllowedTokenCreationDate
        );

        if ($token) {
            return new JsonResponse([
                'token' => [
                    'valid' => true,
                    'email_address' => $token->email_address,
                    'first_name' => null,
                    'last_name' => null,
                ]
            ]);
        }

        // since that didn't find anything either either:
        return $this->fail(Password::BAD_LOGIN_MESSAGE);
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
