<?php

declare(strict_types=1);

namespace BigGive\Identity\Application\Actions;

use Doctrine\DBAL\Exception as DBALException;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpBadRequestException;

#[OA\Get(
    path: '/ping',
    summary: 'Check if the service is running and healthy',
    operationId: 'status',
    responses: [
        new OA\Response(response: 200, description: 'Up and running'),
        new OA\Response(response: 500, description: 'Having some trouble'),
    ],
)]
class Status extends Action
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        LoggerInterface $logger,
    ) {
        parent::__construct($logger);
    }

    /**
     * @param array $args
     * @return Response
     * @throws HttpBadRequestException
     *
     */
    protected function action(Request $request, array $args): Response
    {
        /** @var string|null $errorMessage */
        $errorMessage = null;

        try {
            // getNativeConnection() will connect if needed and throws on failure
            $this->entityManager->getConnection()->getNativeConnection();
        } catch (DBALException $exception) {
            $errorMessage = 'Database connection failed';
        }

        if ($errorMessage === null) {
            return $this->respondWithData(['status' => 'OK']);
        }

        $error = new ActionError(ActionError::SERVER_ERROR, $errorMessage);

        return $this->respond(new ActionPayload(500, ['error' => $errorMessage], $error));
    }
}
