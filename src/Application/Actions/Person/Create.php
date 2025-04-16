<?php

declare(strict_types=1);

namespace BigGive\Identity\Application\Actions\Person;

use Assert\Assertion;
use BigGive\Identity\Application\Actions\Action;
use BigGive\Identity\Application\Actions\ActionError;
use BigGive\Identity\Application\Auth\Token;
use BigGive\Identity\Application\Settings\SettingsInterface;
use BigGive\Identity\Client\Mailer;
use BigGive\Identity\Client\Stripe;
use BigGive\Identity\Domain\DomainException\DuplicateEmailAddressWithPasswordException;
use BigGive\Identity\Domain\EmailVerificationToken;
use BigGive\Identity\Domain\Person;
use BigGive\Identity\Repository\EmailVerificationTokenRepository;
use BigGive\Identity\Repository\PersonRepository;
use Laminas\Diactoros\Response\TextResponse;
use OpenApi\Annotations as OA;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpBadRequestException;
use Stripe\Exception\ApiErrorException;
use Stripe\Service\CustomerService;
use Symfony\Component\Serializer\Encoder\JsonEncode;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use TypeError;

/**
 * @OA\Post(
 *     path="/v1/people",
 *     summary="Create a new Person record",
 *     operationId="person_create",
 *     @OA\RequestBody(
 *         description="All details needed to register a Person, including valid captcha_code",
 *         required=true,
 *         @OA\JsonContent(ref="#/components/schemas/Person")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Registered",
 *         @OA\JsonContent(ref="#/components/schemas/Person"),
 *     ),
 *     @OA\Response(
 *         response=400,
 *         description="Invalid or missing data",
 *         @OA\JsonContent(
 *          format="object",
 *          example={
 *              "error": {
 *                  "description": "The error details",
 *              }
 *          },
 *         ),
 *     ),
 *     @OA\Response(
 *         response=401,
 *         description="Captcha verification failed",
 *     ),
 * ),
 * @see Person
 */
class Create extends Action
{
    public function __construct(
        LoggerInterface $logger,
        private readonly PersonRepository $personRepository,
        private readonly SerializerInterface $serializer,
        private readonly SettingsInterface $settings,
        private readonly Stripe $stripeClient,
        private readonly ValidatorInterface $validator,
        private readonly EmailVerificationTokenRepository $emailVerificationTokenRepository,
        private readonly \DateTimeImmutable $now,
        private Mailer $mailerClient,
    ) {
        parent::__construct($logger);
    }

    public function assertValidEmailVerificationTokenSupplied(
        string $email_address,
        string $tokenSecretSupplied,
        Request $request
    ): void {
        $oldestAllowedTokenCreationDate = EmailVerificationToken::oldestCreationDateForSettingPassword($this->now);

        $token = $this->emailVerificationTokenRepository->findToken(
            email_address: $email_address,
            tokenSecret: $tokenSecretSupplied,
            createdSince: $oldestAllowedTokenCreationDate
        );

        if ($token === null) {
            throw new HttpBadRequestException($request, 'Email verification token error');
        }
    }

    /**
     * @param Request $request
     * @return array{person: Person, error: null}|array{error: Response, person: null}
     */
    public function deserializePerson(Request $request, SerializerInterface $serializer): array
    {
        $body = ((string)$request->getBody());

        try {
            $person = $serializer->deserialize(
                $body,
                Person::class,
                'json'
            );
        } catch (UnexpectedValueException | TypeError $exception) {
            // UnexpectedValueException is the Serializer one, not the global one
            $this->logger->info(sprintf('%s non-serialisable payload was: %s', __CLASS__, $body));

            $message = 'Person Create data deserialise error';
            $exceptionType = get_class($exception);

            $validationError = $this->validationError(
                "$message: $exceptionType - {$exception->getMessage()}",
                $message,
                empty($body), // Suspected bot / junk traffic sometimes sends blank payload.
            );
            return ['error' => $validationError, 'person' => null];
        }

        return ['person' => $person, 'error' => null];
    }

    /**
     * @param array $args
     * @return Response
     * @throws HttpBadRequestException
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
                'Person Create data deserialise error',
            );
        }

        ['person' => $person, 'error' => $validationError] = $this->deserializePerson($request, $this->serializer);
        $tokenSecretSupplied = (string)($requestBody["secretNumber"] ?? null);

        if ($validationError) {
            return $validationError;
        }

        \assert($person !== null);

        $email_address = $person->email_address;

        $rawPassword = (string) ($requestBody['raw_password'] ?? null);

        if ($rawPassword !== '') {
            $hasPassword = true;
            Assertion::allNotEmpty([$email_address, $person->first_name, $person->last_name]);
            \assert($email_address !== null); // Psalm can't understand previous line.

            $this->assertValidEmailVerificationTokenSupplied(
                email_address: $email_address,
                tokenSecretSupplied: $tokenSecretSupplied,
                request: $request
            );
            $person->email_address_verified = $this->now;
            $person->raw_password = $rawPassword;
        } else {
            $hasPassword = false;
            Assertion::null($person->raw_password);
        }

        if ($this->settings->get('friendly_captcha')['bypass']) {
            $person->skipCaptchaPresenceValidation();
        }

        $violations = $this->validator->validate($person, null, ['new']);

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

        try {
            // We can't send the person record to matchbot just yet, as we need to give them a stripe ID first.
            // But we have to persist the person before obtaining a stripe ID because persistance is where we generate
            // their UUID that we need to pass to stripe. So persist, set stripe customer ID, then persist again to
            // send to MB.
            $this->personRepository->persist($person, true);
        } catch (DuplicateEmailAddressWithPasswordException $duplicateException) {
            $this->logger->warning(sprintf(
                '%s failed to persist Person: %s',
                __CLASS__,
                $duplicateException->getMessage(),
            ));

            return $this->validationError(
                logMessage: "Update not valid: {$duplicateException->getMessage()}",
                publicMessage: 'Your password could not be set. There is already a password set for ' .
                'your email address.',
                errorType: ActionError::DUPLICATE_EMAIL_ADDRESS_WITH_PASSWORD,
            );
        }

        try {
            $customer = $this->stripeClient->customers->create([
                'metadata' => [
                    'environment' => getenv('APP_ENV'),
                    'personId' => (string)$person->getId(),
                    ...($hasPassword ? [
                        'hasPasswordSince' => $this->now->format('Y-m-d H:i:s'),
                        'emailAddress' => $email_address,
                    ] : [])
                ],
            ]);
        } catch (ApiErrorException $exception) {
            $logMessage = sprintf('%s Stripe API error: %s', __CLASS__, $exception->getMessage());
            $this->logger->error($logMessage);

            return $this->validationError($logMessage, 'Stripe Customer create API error');
        }

        $person->setStripeCustomerId($customer->id);
        $this->personRepository->persist($person, false);

        $token = Token::create((string)$person->getId(), false, $person->stripe_customer_id);
        $person->addCompletionJWT($token);

        if ($hasPassword) {
            $this->sendRegisteredEmail($person);
        }

        return new TextResponse(
            $this->serializer->serialize(
                $person,
                'json',
                [
                    AbstractNormalizer::IGNORED_ATTRIBUTES => Person::NON_SERIALISED_ATTRIBUTES,
                    JsonEncode::OPTIONS => JSON_FORCE_OBJECT,
                ],
            ),
            200,
            ['content-type' => 'application/json']
        );
    }

    private function sendRegisteredEmail(Person $person): void
    {
        $this->mailerClient->sendEmail($person->toMailerPayload());
    }
}
