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
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @OA\Get(
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
 *     summary="Get a Person",
 *     operationId="person_get",
 *     security={
 *         {"personJWT": {}}
 *     },
 *     @OA\Response(
 *         response=200,
 *         description="Person found",
 *         @OA\JsonContent(ref="#/components/schemas/Person")
 *     ),
 *     @OA\Response(
 *         response=401,
 *         description="JWT token verification failed",
 *     ),
 * ),
 * @see Person
 */
class Get extends Action
{
    public function __construct(
        LoggerInterface $logger,
        private readonly PersonRepository $personRepository,
        private readonly SerializerInterface $serializer,
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
        $person = $this->personRepository->find($this->resolveArg('personId'));
        if (!$person) {
            throw new HttpNotFoundException($this->request, 'Person not found');
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
