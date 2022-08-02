<?php

declare(strict_types=1);

namespace BigGive\Identity\Application\Actions;

use BigGive\Identity\Domain\DomainException\DomainRecordNotFoundException;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpNotFoundException;
use Symfony\Component\Validator\ConstraintViolation;

abstract class Action
{
    protected Request $request;

    protected Response $response;

    protected array $args;

    public function __construct(protected readonly LoggerInterface $logger)
    {
    }

    /**
     * @throws HttpNotFoundException
     * @throws HttpBadRequestException
     */
    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $this->request = $request;
        $this->response = $response;
        $this->args = $args;

        try {
            return $this->action();
        } catch (DomainRecordNotFoundException $e) {
            throw new HttpNotFoundException($this->request, $e->getMessage());
        }
    }

    /**
     * @throws DomainRecordNotFoundException
     * @throws HttpBadRequestException
     */
    abstract protected function action(): Response;

    /**
     * @return array|object
     */
    protected function getFormData()
    {
        return $this->request->getParsedBody();
    }

    /**
     * @return mixed
     * @throws HttpBadRequestException
     */
    protected function resolveArg(string $name)
    {
        if (!isset($this->args[$name])) {
            throw new HttpBadRequestException($this->request, "Could not resolve argument `{$name}`.");
        }

        return $this->args[$name];
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
        $json = json_encode($payload, JSON_PRETTY_PRINT);
        $this->response->getBody()->write($json);

        return $this->response
                    ->withHeader('Content-Type', 'application/json')
                    ->withStatus($payload->getStatusCode());
    }

    /**
     * @param string        $logMessage
     * @param string|null   $publicMessage  Falls back to $logMessage if null.
     * @param bool          $reduceSeverity Whether to log this error only at INFO level. Used to
     *                                      avoid noise from known issues.
     * @return Response with 400 HTTP response code.
     */
    protected function validationError(
        string $logMessage,
        ?string $publicMessage = null,
        bool $reduceSeverity = false,
    ): Response {
        if ($reduceSeverity) {
            $this->logger->info($logMessage);
        } else {
            $this->logger->warning($logMessage);
        }
        $error = new ActionError(ActionError::BAD_REQUEST, $publicMessage ?? $logMessage);

        return $this->respond(new ActionPayload(400, null, $error));
    }

    protected function summariseConstraintViolation(ConstraintViolation $violation): string
    {
        if ($violation->getMessage() === 'This value should not be blank.') {
            return "{$violation->getPropertyPath()} must not be blank";
        }

        return $violation->getMessage();
    }
}
