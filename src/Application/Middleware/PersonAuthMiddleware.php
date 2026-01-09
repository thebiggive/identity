<?php

declare(strict_types=1);

namespace BigGive\Identity\Application\Middleware;

use BigGive\Identity\Application\Auth\TokenService;
use JetBrains\PhpStorm\Pure;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Slim\Routing\RouteContext;

abstract class PersonAuthMiddleware implements MiddlewareInterface
{
    use ErrorTrait;

    /**
     * @return ?bool    The required "complete" property value IF there should be a
     *                  restriction. `null` otherwise.
     */
    abstract protected function getCompletePropertyRequirement(): ?bool;

    /**
     * @psalm-suppress PossiblyUnusedMethod - called by PHP-DI
     */
    #[Pure]
    public function __construct(
        private LoggerInterface $logger,
        private TokenService $tokenService,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $routeContext = RouteContext::fromRequest($request);
        $route = $routeContext->getRoute();
        if (!$route) {
            $this->logger->info('Security: No route in context');
            $this->unauthorised($this->logger, false, $request);
        }

        $personId = $route->getArgument('personId');
        $jws = $request->getHeaderLine('x-tbg-auth');

        if (empty($jws)) {
            $this->logger->info('Security: No JWT provided');
            $this->unauthorised($this->logger, true, $request);
        }

        if (! $this->tokenService->check($personId, $this->getCompletePropertyRequirement(), $jws, $this->logger)) {
            $this->unauthorised($this->logger, false, $request);
        }

        return $handler->handle($request);
    }
}
