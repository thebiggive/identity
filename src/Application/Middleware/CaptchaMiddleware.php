<?php

declare(strict_types=1);

namespace BigGive\Identity\Application\Middleware;

use BigGive\Identity\Application\Settings\SettingsInterface;
use JetBrains\PhpStorm\Pure;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use ReCaptcha\ReCaptcha;
use Symfony\Component\Serializer\SerializerInterface;

abstract class CaptchaMiddleware implements MiddlewareInterface
{
    use ErrorTrait;

    /**
     * @psalm-suppress PossiblyUnusedMethod - constructor called by framework
     */
    #[Pure]
    public function __construct(
        private readonly LoggerInterface $logger,
        protected SerializerInterface $serializer,
        protected readonly SettingsInterface $settings,
        private FriendlyCaptchaVerifier $friendlyCaptchaVerifier,
    ) {
    }

    /**
     * @throws \Slim\Exception\HttpUnauthorizedException on verification errors.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->settings->get('friendly_captcha')['bypass']) {
            $this->logger->warning('Recaptcha verification bypassed');
            return $handler->handle($request);
        }

        $captchaCode = $this->getCode($request);
        if ($captchaCode === null) {
            $this->logger->log(LogLevel::WARNING, 'Security: captcha code not sent');
            $this->unauthorised($this->logger, true, $request);
        }

        if (!$this->friendlyCaptchaVerifier->verify($captchaCode)) {
            $this->unauthorised($this->logger, true, $request);
        }

        return $handler->handle($request);
    }

    abstract protected function getCode(ServerRequestInterface $request): ?string;
}
