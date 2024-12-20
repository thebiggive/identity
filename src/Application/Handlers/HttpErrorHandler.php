<?php

declare(strict_types=1);

namespace BigGive\Identity\Application\Handlers;

use BigGive\Identity\Application\Actions\ActionError;
use BigGive\Identity\Application\Actions\ActionPayload;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpException;
use Slim\Exception\HttpForbiddenException;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Slim\Exception\HttpNotImplementedException;
use Slim\Exception\HttpUnauthorizedException;
use Slim\Handlers\ErrorHandler as SlimErrorHandler;
use Throwable;

class HttpErrorHandler extends SlimErrorHandler
{
    /**
     * @inheritdoc
     */
    protected function respond(): Response
    {
        $exception = $this->exception;
        $statusCode = 500;
        $error = $this->createInternalError(
            'An internal error has occurred while processing your request.',
            $this->displayErrorDetails ? $exception->getTrace() : null
        );

        $this->logger->info('HttpErrorHandler exception: ' . $this->exception->getMessage());

        if ($exception instanceof HttpException) {
            $statusCode = $exception->getCode();
            $error->setDescription($exception->getMessage());

            if ($exception instanceof HttpNotFoundException) {
                $error->setType(ActionError::RESOURCE_NOT_FOUND);
            } elseif ($exception instanceof HttpMethodNotAllowedException) {
                $error->setType(ActionError::NOT_ALLOWED);
            } elseif ($exception instanceof HttpUnauthorizedException) {
                $error->setType(ActionError::UNAUTHENTICATED);
            } elseif ($exception instanceof HttpForbiddenException) {
                $error->setType(ActionError::INSUFFICIENT_PRIVILEGES);
            } elseif ($exception instanceof HttpBadRequestException) {
                $error->setType(ActionError::BAD_REQUEST);
            } elseif ($exception instanceof HttpNotImplementedException) {
                $error->setType(ActionError::NOT_IMPLEMENTED);
            }
        }

        if (
            !($exception instanceof HttpException)
            && $exception instanceof Throwable
            && $this->displayErrorDetails
        ) {
            $error->setDescription($exception->getMessage());
        }

        $payload = new ActionPayload($statusCode, null, $error);
        try {
            $encodedPayload = json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            $this->logger->warning(sprintf(
                'Original error is not JSON so cannot be returned verbatim: %s',
                $exception->getMessage(),
            ));
            $payload = new ActionPayload(
                $statusCode,
                null,
                $this->createInternalError('Original error is not encodable as JSON, see info logs'),
            );
            $encodedPayload = json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        }

        $response = $this->responseFactory->createResponse($statusCode);

        $response->getBody()->write($encodedPayload);

        return $response->withHeader('Content-Type', 'application/json');
    }

    private function createInternalError(string $message, array|null $trace = null): ActionError
    {
        return new ActionError(
            ActionError::SERVER_ERROR,
            $message,
            $trace
        );
    }
}
