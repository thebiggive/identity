<?php

declare(strict_types=1);

namespace BigGive\Identity\Application\Actions\Person;

use BigGive\Identity\Application\Actions\Action;
use Assert\Assertion;
use BigGive\Identity\Application\Security\EmailVerificationService;
use BigGive\Identity\Client;
use BigGive\Identity\Domain\Person;
use BigGive\Identity\Repository\PersonRepository;
use Laminas\Diactoros\Response\TextResponse;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpNotFoundException;
use Symfony\Component\Serializer\Encoder\JsonEncode;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\UidNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use TypeError;

/**
 * @see Person
 */
#[OA\Put(
    path: '/v1/people/{personId}',
    summary: 'Update a Person, e.g. to set name and email address - but may not be used to set or change a password',
    operationId: 'person_update',
    security: [['personJWT' => []]],
    parameters: [
        new OA\PathParameter(
            name: 'personId',
            description: 'UUID of the person to update',
            schema: new OA\Schema(
                type: 'string',
                format: 'uuid',
                example: 'f7095caf-7180-4ddf-a212-44bacde69066',
                pattern: '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}',
            ),
        ),
    ],
    requestBody: new OA\RequestBody(
        description: 'All details needed to register a Person',
        required: true,
        content: new OA\JsonContent(ref: '#/components/schemas/Person'),
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Registered',
            content: new OA\JsonContent(ref: '#/components/schemas/Person'),
        ),
        new OA\Response(
            response: 400,
            description: 'Invalid or missing data',
            content: new OA\JsonContent(
                format: 'object',
                example: [
                    'error' => [
                        'type' => 'DUPLICATE_EMAIL_ADDRESS_WITH_PASSWORD',
                        'description' => 'The error details',
                    ],
                ],
            ),
        ),
        new OA\Response(response: 401, description: 'JWT token verification failed'),
    ],
)]
class Update extends Action
{
    public function __construct(
        LoggerInterface $logger,
        private readonly PersonRepository $personRepository,
        private readonly SerializerInterface $serializer,
        private readonly Client\Stripe $stripeClient,
        private readonly ValidatorInterface $validator,
        private readonly EmailVerificationService $emailVerificationService,
    ) {
        parent::__construct($logger);
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

        // `has_password` on the person object is only set when the Normalizer's run.
        $hasPassword = $person->getPasswordHash() !== null;

        $body = ((string) $request->getBody());
        try {
            $this->serializer->deserialize(
                $body,
                Person::class,
                'json',
                [
                    AbstractNormalizer::ATTRIBUTES => Person::SERIALISED_FOR_UPDATE_ATTRIBUTES,
                    AbstractNormalizer::OBJECT_TO_POPULATE => $person,
                    UidNormalizer::NORMALIZATION_FORMAT_CANONICAL => UidNormalizer::NORMALIZATION_FORMAT_RFC4122,
                ],
            );

            // don't deserialize raw password as we have other way to handle password changes.
            Assertion::null($person->raw_password);
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

        $violations = $this->validator->validate($person, null, [Person::VALIDATION_COMPLETE]);

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

        $this->personRepository->persist($person, false);

        $params = $person->getStripeCustomerParams();

        unset($params['test_clock']); // these params not accepted by stripe for updated, and stripe library is now
        // strictly typed.
        unset($params['payment_method']);
        unset($params['tax_id_data']);

        $this->stripeClient->customers->update($person->stripe_customer_id, $params);

        if ($person->email_address !== null && !$hasPassword) {
            // Often stores 2 or 3 tokens during a donation as each change to the Person calls it afresh.
            $this->emailVerificationService->storeTokenForEmail($person->email_address);
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
