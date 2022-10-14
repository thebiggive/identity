<?php

declare(strict_types=1);

namespace BigGive\Identity\Application\Handlers;

use BigGive\Identity\Application\ResponseEmitter\ResponseEmitter;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpInternalServerErrorException;

class ShutdownHandler
{
    private Request $request;

    private HttpErrorHandler $errorHandler;

    private bool $displayErrorDetails;

    public function __construct(
        Request $request,
        HttpErrorHandler $errorHandler,
        bool $displayErrorDetails
    ) {
        $this->request = $request;
        $this->errorHandler = $errorHandler;
        $this->displayErrorDetails = $displayErrorDetails;
    }

    public function __invoke()
    {
        $error = error_get_last();
        if ($error) {
            $errorFile = $error['file'];
            $errorLine = $error['line'];
            $errorMessage = $error['message'];
            $errorType = $error['type'];
            $message = 'An error while processing your request. Please try again later.';

            if ($this->displayErrorDetails) {
                switch ($errorType) {
                    case E_USER_ERROR:
                        $message = "FATAL ERROR: {$errorMessage}. ";
                        $message .= " on line {$errorLine} in file {$errorFile}.";
                        break;

                    case E_USER_WARNING:
                        $message = "WARNING: {$errorMessage}";
                        break;

                    case E_USER_NOTICE:
                        $message = "NOTICE: {$errorMessage}";
                        break;

                    default:
                        $message = "ERROR: {$errorMessage}";
                        $message .= " on line {$errorLine} in file {$errorFile}.";
                        break;
                }
            }

            // Skip emitting a shutdown response from native warnings on non-dev envs, since events like Redis
            // connection failures cause these. These are already logged and if error-like output is emitted
            // alongside `/ping`'s more helpful output, its response body is left malformatted.
            $isServiceResolutionWarning = (
                $errorType === E_WARNING &&
                str_contains($message, 'getaddrinfo failed: Name or service not known')
            );
            if ($isServiceResolutionWarning) {
                return;
            }

            if ($errorType === E_DEPRECATED) {
                // Don't let deprecations trigger `HttpInternalServerErrorException`, at least for as
                // long as we know our rate limit middleware has one in PHP 8.1.
                // See https://github.com/Lansoweb/LosRateLimit/issues/11
                return;
            }

            $exception = new HttpInternalServerErrorException($this->request, $message);
            $response = $this->errorHandler->__invoke(
                $this->request,
                $exception,
                $this->displayErrorDetails,
                true,
                true,
            );

            $responseEmitter = new ResponseEmitter();
            $responseEmitter->emit($response);
        }
    }
}
