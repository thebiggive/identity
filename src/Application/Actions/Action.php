<?php

declare(strict_types=1);

namespace BigGive\Identity\Application\Actions;

use BigGive\Identity\Domain\DomainException\DomainRecordNotFoundException;
use OpenApi\Annotations as OA;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpNotFoundException;
use Symfony\Component\Validator\ConstraintViolation;

/**
 * @OA\Info(title="Big Give Identity service", version="0.0.6"),
 * @OA\Server(
 *     description="Staging",
 *     url="https://identity-staging.thebiggivetest.org.uk",
 * ),
 * @OA\SecurityScheme(
 *     securityScheme="personJWT",
 *     type="apiKey",
 *     in="header",
 *     name="x-tbg-auth",
 * ),
 *
 * Swagger Hub doesn't (yet?) support `"bearerFormat": "JWT"`.
 */
abstract class Action
{
    public function __construct(protected readonly LoggerInterface $logger)
    {
    }

    /**
     * @throws HttpNotFoundException
     * @throws HttpBadRequestException
     */
    public function __invoke(Request $request, Response $response, array $args): Response
    {
        try {
            return $this->action($request, $args);
        } catch (DomainRecordNotFoundException $e) {
            throw new HttpNotFoundException($request, $e->getMessage());
        }
    }

    /**
     * @param array $args
     * @throws DomainRecordNotFoundException
     * @throws HttpBadRequestException
     */
    abstract protected function action(Request $request, array $args): Response;

    /**
     * @return mixed
     * @throws HttpBadRequestException
     */
    protected function resolveArg(array $args, Request $request, string $name)
    {
        if (!isset($args[$name])) {
            throw new HttpBadRequestException($request, "Could not resolve argument `{$name}`.");
        }

        return $args[$name];
    }

    /**
     * @param array|object|null $data
     */
    protected function respondWithData($data = null, int $statusCode = 200): Response
    {
        $payload = new ActionPayload($statusCode, $data);

        return $this->respond($payload);
    }

    protected function respond(ActionPayload $payload): Response
    {
        $response = new \Slim\Psr7\Response();
        $json = json_encode($payload, JSON_PRETTY_PRINT);
        $response->getBody()->write($json);

        return $response
                    ->withHeader('Content-Type', 'application/json')
                    ->withStatus($payload->getStatusCode());
    }

    /**
     * @param string        $logMessage
     * @param string|null   $publicMessage  Falls back to $logMessage if null.
     * @param bool          $reduceSeverity Whether to log this error only at INFO level. Used to
     *                                      avoid noise from known issues.
     * @param int|null      $httpCode       Falls back to 400 if null.
     * @return Response with 400 (or custom) HTTP response code.
     */
    protected function validationError(
        string $logMessage,
        ?string $publicMessage = null,
        bool $reduceSeverity = false,
        ?int $httpCode = 400,
    ): Response {
        if ($reduceSeverity) {
            $this->logger->info($logMessage);
        } else {
            $this->logger->warning($logMessage);
        }
        $error = new ActionError(
            $httpCode === 401 ? ActionError::VALIDATION_ERROR : ActionError::BAD_REQUEST,
            $publicMessage ?? $logMessage,
        );

        return $this->respond(new ActionPayload($httpCode, null, $error));
    }

    protected function summariseConstraintViolation(ConstraintViolation $violation): string
    {
        if ($violation->getMessage() === 'This value should not be blank.') {
            return "{$violation->getPropertyPath()} must not be blank";
        }

        return $violation->getMessage();
    }
}
