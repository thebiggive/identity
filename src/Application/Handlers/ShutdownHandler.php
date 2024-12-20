<?php

declare(strict_types=1);

namespace BigGive\Identity\Application\Handlers;

use JetBrains\PhpStorm\Pure;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpInternalServerErrorException;
use Slim\ResponseEmitter;

class ShutdownHandler
{
    #[Pure]
    public function __construct(
        private Request $request,
        private HttpErrorHandler $errorHandler,
        private bool $displayErrorDetails
    ) {
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

            if (in_array($errorType, [\E_DEPRECATED, \E_USER_DEPRECATED], true)) {
                // Deprecations should not be shown as errors to users - we just have to
                // fix them before we upgrade PHP or the dependancy that triggered them.

                // E.g. symfony creates E_USER_DEPRECATED error types in advance of removing
                // support for a feature or mode.
                return;
            }

            $exception = new HttpInternalServerErrorException($this->request, $message);
            $response = $this->errorHandler->__invoke(
                request: $this->request,
                exception: $exception,
                displayErrorDetails: $this->displayErrorDetails,
                // Don't log via the less flexible built-in Slim fn; HttpErrorHandler does it via LoggerInterface
                logErrors: false,
                logErrorDetails: false,
            );

            $responseEmitter = new ResponseEmitter();
            $responseEmitter->emit($response);
        }
    }
}
