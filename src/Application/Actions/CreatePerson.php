<?php

declare(strict_types=1);

namespace BigGive\Identity\Application\Actions;

use BigGive\Identity\Application\Settings\SettingsInterface;
use BigGive\Identity\Domain\Person;
use BigGive\Identity\Repository\PersonRepository;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use BigGive\Identity\Client\BadRequestException;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpBadRequestException;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
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
 */
class CreatePerson extends Action
{
    public function __construct(
        LoggerInterface $logger,
        private readonly PersonRepository $personRepository,
        private readonly SerializerInterface $serializer,
        private readonly SettingsInterface $settings,
        private readonly ValidatorInterface $validator,
    ) {
        parent::__construct($logger);
    }

    /**
     * @return Response
     * @throws HttpBadRequestException
     * @throws GuzzleException
     * @throws BadRequestException
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

        $violations = $this->validator->validate($person);

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

        // After persisting the Person, send them a registration success email
        try {
            $this->sendRegistrationSuccessEmail($person);
        } catch (RequestException $ex) {
            $this->logger->error(sprintf(
                'Donor registration email exception %s with error code %s: %s. Body: %s',
                get_class($ex),
                $ex->getCode(),
                $ex->getMessage(),
                $ex->getResponse() ? $ex->getResponse()->getBody() : 'N/A',
            ));

            throw new BadRequestException('Failed to send registration success email to newly registered donor.');
        }

        return new JsonResponse($person->jsonSerialize());
    }

    /**
     * @param Person $person
     * @return void
     * @throws GuzzleException
     * @throws RequestException
     */
    public function sendRegistrationSuccessEmail(Person $person): void
    {
        $this->httpClient = new Client([
            'timeout' => $this->settings->get('apiClient')['global']['timeout'],
        ]);

        $requestBody = $person->toMailerPayload();

        $this->httpClient->post(
            $this->settings->get('apiClient')['mailer']['baseUri'] . '/v1/send',
            [
                'json' => $requestBody,
                'headers' => [
                    'x-send-verify-hash' => $this->hash(json_encode($requestBody)),
                ],
            ]
        );
    }

    private function hash(string $body): string
    {
        return hash_hmac('sha256', trim($body), $this->settings->get('apiClient')['mailer']['sendSecret']);
    }
}
