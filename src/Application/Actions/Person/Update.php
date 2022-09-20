<?php

declare(strict_types=1);

namespace BigGive\Identity\Application\Actions\Person;

use BigGive\Identity\Application\Actions\Action;
use BigGive\Identity\Domain\Person;
use BigGive\Identity\Repository\PersonRepository;
use Laminas\Diactoros\Response\TextResponse;
use OpenApi\Annotations as OA;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpNotFoundException;
use Stripe\StripeClient;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\UidNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use TypeError;

/**
 * @OA\Put(
 *     path="/v1/people/{personId}",
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

    /**
     * @return Response
     * @throws HttpBadRequestException
     * @throws HttpNotFoundException
     */
    protected function action(): Response
    {
        $person = $this->personRepository->find($this->request->getAttribute('personId'));
        if (!$person) {
            throw new HttpNotFoundException($this->request, 'Person not found');
        }

        try {
            /** @var Person $person */
            $person = $this->serializer->deserialize(
                $body = ((string) $this->request->getBody()),
                Person::class,
                'json',
                [
                    AbstractNormalizer::IGNORED_ATTRIBUTES => Person::NON_SERIALISED_ATTRIBUTES,
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

        $person = $this->personRepository->persist($person);

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

        return new TextResponse(
            $this->serializer->serialize(
                $person,
                'json',
                [
                    AbstractNormalizer::IGNORED_ATTRIBUTES => Person::NON_SERIALISED_ATTRIBUTES,
                ],
            ),
            200,
            ['content-type' => 'application/json']
        );
    }
}
