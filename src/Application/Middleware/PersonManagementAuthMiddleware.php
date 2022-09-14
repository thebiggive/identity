<?php

declare(strict_types=1);

namespace BigGive\Identity\Application\Middleware;

use BigGive\Identity\Application\Auth\Token;
use JetBrains\PhpStorm\Pure;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Slim\Routing\RouteContext;

class PersonManagementAuthMiddleware implements MiddlewareInterface
{
    use ErrorTrait;

    #[Pure]
    public function __construct(
        private LoggerInterface $logger
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

        // This is used just for setting personal info + password for now. We require the token to have
        // been issued for managing a *not* complete Person record.
        if (!Token::check($personId, false, $jws, $this->logger)) {
            $this->unauthorised($this->logger, false, $request);
        }

        return $handler->handle($request);
    }
}
