<?php

declare(strict_types=1);

namespace BigGive\Identity\Application\Actions\Person;

use BigGive\Identity\Application\Actions\Action;
use BigGive\Identity\Application\Actions\ActionError;
use BigGive\Identity\Domain\DomainException\DuplicateEmailAddressWithPasswordException;
use BigGive\Identity\Domain\Person;
use BigGive\Identity\Repository\PersonRepository;
use Laminas\Diactoros\Response\TextResponse;
use OpenApi\Annotations as OA;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpNotFoundException;
use Stripe\StripeClient;
use Symfony\Component\Serializer\Encoder\JsonEncode;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\UidNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use TypeError;

/**
 * @OA\Put(
 *     path="/v1/people/{personId}",
 *     @OA\PathParameter(
 *         name="personId",
 *         description="UUID of the person to update",
 *         @OA\Schema(
 *             type="string",
 *             format="uuid",
 *             example="f7095caf-7180-4ddf-a212-44bacde69066",
 *             pattern="[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}",
 *         ),
 *     ),
 *     summary="Update a Person, e.g. to set a password",
 *     operationId="person_update",
 *     security={
 *         {"personJWT": {}}
 *     },
 *     @OA\RequestBody(
 *         description="All details needed to register a Person",
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
 *                  "type": "DUPLICATE_EMAIL_ADDRESS_WITH_PASSWORD",
 *                  "description": "The error details",
 *              }
 *          },
 *         ),
 *     ),
 *     @OA\Response(
 *         response=401,
 *         description="JWT token verification failed",
 *     ),
 * ),
 * @see Person
 */
class Update extends Action
{
    public function __construct(
        LoggerInterface $logger,
        private readonly PersonRepository $personRepository,
        private readonly SerializerInterface $serializer,
        private readonly StripeClient $stripeClient,
        private readonly ValidatorInterface $validator,
    ) {
        parent::__construct($logger);
    }

    public function violationsToPlainText(ConstraintViolationListInterface $violations): string
    {
        $violationDetails = [];
        foreach ($violations as $violation) {
            $violationDetails[] = $this->summariseConstraintViolation($violation);
        }

        return implode('; ', $violationDetails);
    }

    public function violationsToHtml(ConstraintViolationListInterface $violations): string
    {
        $violationDetails = [];
        foreach ($violations as $violation) {
            $violationDetails[] = $this->summariseConstraintViolationAsHtmlSnippet($violation);
        }

        return implode('; ', $violationDetails);
    }

    /**
     * @param array $args
     * @return Response
     * @throws HttpNotFoundException
     * @throws \Exception on email callout errors
     */
    protected function action(Request $request, array $args): Response
    {
        $person = $this->personRepository->find($this->resolveArg($args, $request, 'personId'));
        if (!$person) {
            throw new HttpNotFoundException($request, 'Person not found');
        }

        // `has_password` is only set when the Normalizer's run.
        $personHadPassword = $person->getPasswordHash() !== null;

        try {
            /** @var Person $person */
            $this->serializer->deserialize(
                $body = ((string) $request->getBody()),
                Person::class,
                'json',
                [
                    AbstractNormalizer::ATTRIBUTES => Person::SERIALISED_FOR_UPDATE_ATTRIBUTES,
                    AbstractNormalizer::OBJECT_TO_POPULATE => $person,
                    UidNormalizer::NORMALIZATION_FORMAT_CANONICAL => UidNormalizer::NORMALIZATION_FORMAT_RFC4122,
                ],
            );
        } catch (UnexpectedValueException | TypeError $exception) {
            // UnexpectedValueException is the Serializer one, not the global one
            $this->logger->info(sprintf('%s non-serialisable payload was: %s', __CLASS__, $body));

            $message = 'Person Update data deserialise error';
            $exceptionType = get_class($exception);

            return $this->validationError(
                "$message: $exceptionType - {$exception->getMessage()}",
                $message,
                empty($body), // Suspected bot / junk traffic sometimes sends blank payload.
            );
        }

        $violations = $this->validator->validate($person, null, ['complete']);

        if (count($violations) > 0) {
            $message = $this->violationsToPlainText($violations);
            $htmlMessage = $this->violationsToHtml($violations);

            return $this->validationError(
                $message,
                null,
                true,
                htmlMessage: $htmlMessage,
            );
        }

        // We should persist Stripe's Customer ID on initial Person create.
        \assert(is_string($person->stripe_customer_id));

        try {
            $this->personRepository->persist($person);
        } catch (DuplicateEmailAddressWithPasswordException $duplicateException) {
            $this->logger->warning(sprintf(
                '%s failed to persist Person: %s',
                __CLASS__,
                $duplicateException->getMessage(),
            ));

            return $this->validationError(
                logMessage: "Update not valid: {$duplicateException->getMessage()}",
                publicMessage: "Your password could not be set. There is already a password set for your email address.",
                errorType: ActionError::DUPLICATE_EMAIL_ADDRESS_WITH_PASSWORD,
            );
        }

        $personHasPasswordNow = $person->getPasswordHash() !== null;
        $customerDetails = [
            'email' => $person->email_address,
            'name' => sprintf('%s %s', $person->first_name, $person->last_name),
        ];

        // Billing address can vary per payment method and is best kept against that object as it's
        // the only thing we know the address matches.
        // "Home address" is collected only for Gift Aid declarations and is optional, so append it conditionally.
        if (!empty($person->home_address_line_1)) {
            $customerDetails['address'] = [
                'line1' => $person->home_address_line_1,
            ];

            if (!empty($person->home_postcode)) {
                $customerDetails['address']['postal_code'] = $person->home_postcode;

                // Should be 'GB' when postcode non-null.
                $customerDetails['address']['country'] = $person->home_country_code;
            }
        }

        $this->stripeClient->customers->update($person->stripe_customer_id, $customerDetails);

        if (!$personHadPassword && $personHasPasswordNow) {
            $customerDetails['metadata']['hasPasswordSince'] = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
            $this->stripeClient->customers->update($person->stripe_customer_id, $customerDetails);

            // If the person didn't have a password before, but now does, send them a welcome email.
            if (!$this->personRepository->sendRegisteredEmail($person)) {
                throw new \Exception('Failed to send registration success email');
            }
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
}
