<?php

declare(strict_types=1);

namespace BigGive\Identity\Application\Actions;

use BigGive\Identity\Domain\Person;
use Doctrine\Common\Proxy\ProxyGenerator;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Annotations as OA;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpBadRequestException;

/**
 * @OA\Get(
 *     path="/ping",
 *     summary="Check if the service is running and healthy",
 *     operationId="status",
 *     @OA\Response(
 *         response=200,
 *         description="Up and running",
 *     ),
 *     @OA\Response(
 *         response=500,
 *         description="Having some trouble",
 *     ),
 * ),
 */
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
     */
    protected function action(Request $request, array $args): Response
    {
        /** @var string|null $errorMessage */
        $errorMessage = null;

        try {
            $gotDbConnection = (
                $this->entityManager->getConnection()->isConnected() ||
                $this->entityManager->getConnection()->connect()
            );
            if (!$gotDbConnection) {
                $errorMessage = 'Database not connected';
            }
        } catch (DBALException $exception) {
            $errorMessage = 'Database connection failed';
        }

        if ($errorMessage === null && !$this->checkCriticalModelDoctrineProxies()) {
            $errorMessage = 'Doctrine proxies not built';
        }

        if ($errorMessage === null) {
            return $this->respondWithData(['status' => 'OK']);
        }

        $error = new ActionError(ActionError::SERVER_ERROR, $errorMessage);

        return $this->respond(new ActionPayload(500, ['error' => $errorMessage], $error));
    }

    /**
     * @return bool Whether all needed proxies are present.
     */
    private function checkCriticalModelDoctrineProxies(): bool
    {
        // Concrete, core mapped app models only.
        $criticalModelClasses = [
            Person::class,
        ];

        // A separate ProxyGenerator with the same proxy dir and proxy namespace should produce the paths we need to
        // test for. We can't call the one inside the EM's ProxyFactory because it's private and we don't want to
        // call the public method that regenerates proxies, since in deployed ECS envs we set files to be immutable
        // and expect to generate things only in the `deploy/` entrypoints.
        $emConfig = $this->entityManager->getConfiguration();
        $proxyGenerator = new ProxyGenerator($emConfig->getProxyDir(), $emConfig->getProxyNamespace());
        foreach ($criticalModelClasses as $modelClass) {
            $expectedFile = $proxyGenerator->getProxyFileName($modelClass);

            if (!file_exists($expectedFile)) {
                return false;
            }
        }

        return true;
    }
}
