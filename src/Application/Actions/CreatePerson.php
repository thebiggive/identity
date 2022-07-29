<?php

declare(strict_types=1);

namespace BigGive\Identity\Application\Actions;

use BigGive\Identity\Domain\Person;
use Doctrine\ORM\EntityManagerInterface;
use Laminas\Diactoros\Response\JsonResponse;
use OpenApi\Annotations as OA;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpBadRequestException;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Symfony\Component\Serializer\SerializerInterface;
use TypeError;

/**
 * Creates a new Person record.
 */
class CreatePerson extends Action
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        LoggerInterface $logger,
        private readonly SerializerInterface $serializer,
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
        }

        $person->hashPassword();

        $this->entityManager->persist($person);
        $this->entityManager->flush();

        return new JsonResponse($person->jsonSerialize());
    }
}
