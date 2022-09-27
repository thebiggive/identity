<?php

declare(strict_types=1);

namespace BigGive\Identity\Application\Actions\Person;

use BigGive\Identity\Application\Actions\Action;
use BigGive\Identity\Application\Auth\Token;
use BigGive\Identity\Client\Mailer;
use BigGive\Identity\Domain\Person;
use BigGive\Identity\Repository\PersonRepository;
use Laminas\Diactoros\Response\TextResponse;
use OpenApi\Annotations as OA;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpBadRequestException;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;
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
 *         description="Captcha verification failed",
 *     ),
 * ),
 * @see Person
 */
class Create extends Action
{
    public function __construct(
        LoggerInterface $logger,
        private readonly Mailer $mailerClient,
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
     */
    protected function action(): Response
    {
        try {
            /** @var Person $person */
            $person = $this->serializer->deserialize(
                $body = ((string) $this->request->getBody()),
                Person::class,
                'json'
            );
        } catch (UnexpectedValueException | TypeError $exception) {
            // UnexpectedValueException is the Serializer one, not the global one
            $this->logger->info(sprintf('%s non-serialisable payload was: %s', __CLASS__, $body));

            $message = 'Person Create data deserialise error';
            $exceptionType = get_class($exception);

            return $this->validationError(
                "$message: $exceptionType - {$exception->getMessage()}",
                $message,
                empty($body), // Suspected bot / junk traffic sometimes sends blank payload.
            );
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

        $person = $this->personRepository->persist($person);

        try {
            $customer = $this->stripeClient->customers->create([
                'metadata' => [
                    'environment'   => getenv('APP_ENV'),
                    'personId'      => (string) $person->getId(),
                ],
            ]);
        } catch (ApiErrorException $exception) {
            $logMessage = sprintf('%s Stripe API error: %s', __CLASS__, $exception->getMessage());
            $this->logger->error($logMessage);

            return $this->validationError($logMessage, 'Stripe Customer create API error');
        }

        $person->setStripeCustomerId($customer->id);
        $this->personRepository->persist($person);

        $token = Token::create((string) $person->getId(), false, $person->stripe_customer_id);
        $person->addCompletionJWT($token);

        // After completely persisting Person, send them a registration success email
        $requestBody = $person->toMailerPayload();
        $sendSuccessful = $this->mailerClient->sendEmail($requestBody);

        if (!$sendSuccessful) {
            throw new HttpBadRequestException($this->request, 'Failed to send registration success email to newly registered donor.');
        }

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
