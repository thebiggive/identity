<?php

declare(strict_types=1);

namespace BigGive\Identity\Application\Actions;

use BigGive\Identity\Domain\Person;
use BigGive\Identity\Repository\PersonRepository;
use Laminas\Diactoros\Response\JsonResponse;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpBadRequestException;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use TypeError;

/**
 * #[OA\Post(
 *     path="/v1/people",
 *     summary="Create a new Person record",
 *     operationId="person_create",
 *     [new OA\RequestBody(
 *         description="All details needed to register a Person",
 *         required=true,
 *         [new OA\JsonContent(ref="#/components/schemas/Person")],
 *     )],
 *     [new OA\Response(
 *         response=200,
 *         description="Registered",
 *         [new OA\JsonContent(ref="#/components/schemas/Person")],
 *     )],
 *     [new OA\Response(
 *         response=400,
 *         description="Invalid or missing data",
 *         [new OA\JsonContent(
 *          format="object",
 *          example={
 *              "error": {
 *                  "description": "The error details",
 *              }
 *          },
 *         )],
 *     )],
 *     [new OA\Response(
 *         response=401,
 *         description="Captcha verification failed",
 *     )],
 * )],
 */
class CreatePerson extends Action
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

        return new JsonResponse($person->jsonSerialize());
    }
}
