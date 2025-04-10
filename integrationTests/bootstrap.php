<?php

use BigGive\Identity\IntegrationTests\IntegrationTest;
use LosMiddleware\RateLimit\RateLimitMiddleware;
use LosMiddleware\RateLimit\RateLimitOptions;
use Mezzio\ProblemDetails\ProblemDetailsResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Factory\AppFactory;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Transport\TransportInterface;

require __DIR__ . '/../vendor/autoload.php';

if (! in_array(getenv('APP_ENV'), ['local', 'test'])) {
    throw new \Exception("Don't run integration tests in live!");
}

$container = require __DIR__ . '/../bootstrap.php';
IntegrationTest::setContainer($container);

// Instantiate the app
AppFactory::setContainer($container);
$app = AppFactory::create();

/** @psalm-suppress MixedArgument */
$container->set(RateLimitMiddleware::class, new class (
    $container->get(Psr16Cache::class),
    $container->get(ProblemDetailsResponseFactory::class),
    new RateLimitOptions(),
) extends RateLimitMiddleware {
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // skip rate limiting for tests.
        return $handler->handle($request);
    }
});

$container->set(TransportInterface::class, new InmemoryTransport());

// Register routes
$routes = require __DIR__ . '/../app/routes.php';
$routes($app);

IntegrationTest::setApp($app);
