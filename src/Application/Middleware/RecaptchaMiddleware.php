<?php

declare(strict_types=1);

namespace BigGive\Identity\Application\Middleware;

use BigGive\Identity\Domain\Person;
use JetBrains\PhpStorm\Pure;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use ReCaptcha\ReCaptcha;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Symfony\Component\Serializer\SerializerInterface;

abstract class RecaptchaMiddleware implements MiddlewareInterface
{
    use ErrorTrait;

    #[Pure]
    public function __construct(
        private LoggerInterface $logger,
        private ReCaptcha $captcha,
        protected SerializerInterface $serializer,
    ) {
    }

    /**
     * @throws \Slim\Exception\HttpUnauthorizedException on verification errors.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $timesToAttemptCaptchaVerification = 2;

        for ($counter = 0; $counter < $timesToAttemptCaptchaVerification; $counter++) {
            $captchaCode = $this->getCode($request);

            $result = $this->captcha->verify(
                $captchaCode,
                $request->getAttribute('client-ip') // Set to original IP by previous middleware
            );

            if ($result->isSuccess()) {
                break; // Leave loop and let `$handler` do its thing.
            }

            $errors = $result->getErrorCodes();
            $isConnectionError = in_array(ReCaptcha::E_CONNECTION_FAILED, $errors, true);

            // Connection errors bubbled up from cURL are potentially worth a retry – they might not have reached
            // the reCAPTCHA server. Any other failure is going to fail again because of the restrictions to
            // prevent replay attacks. https://developers.google.com/recaptcha/docs/verify#token_restrictions
            $this->logger->log(
                $isConnectionError ? LogLevel::INFO : LogLevel::WARNING,
                'Security: captcha failed, attempt: ' . ($counter + 1) . '. Error codes: ' . json_encode($errors),
            );

            if (!$isConnectionError) {
                $this->unauthorised($this->logger, true, $request);
            }

            if ($counter >= ($timesToAttemptCaptchaVerification - 1)) {
                $this->logger->warning('Warning: captcha verification has now failed after '
                . $timesToAttemptCaptchaVerification . ' attempts!');
                $this->unauthorised($this->logger, true, $request);
            }
        }

        return $handler->handle($request);
    }

    abstract protected function getCode(ServerRequestInterface $request): string;
}
