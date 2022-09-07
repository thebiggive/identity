<?php

declare(strict_types=1);

namespace BigGive\Identity\Tests;

use DI\ContainerBuilder;
use Exception;
use PHPUnit\Framework\TestCase as PHPUnit_TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Http\Message\ServerRequestInterface;
use ReCaptcha\ReCaptcha;
use Redis;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Headers;
use Slim\Psr7\Request as SlimRequest;
use Slim\Psr7\Uri;

class TestCase extends PHPUnit_TestCase
{
    use ProphecyTrait;

    /**
     * @return App
     * @throws Exception
     */
    protected function getAppInstance(): App
    {
        // Instantiate PHP-DI ContainerBuilder
        $containerBuilder = new ContainerBuilder();

        // Container intentionally not compiled for tests.

        // Set up settings
        $settings = require __DIR__ . '/../app/settings.php';
        $settings($containerBuilder);

        // Set up dependencies
        $dependencies = require __DIR__ . '/../app/dependencies.php';
        $dependencies($containerBuilder);

        $repositories = require __DIR__ . '/../app/repositories.php';
        $repositories($containerBuilder);

        // Build PHP-DI Container instance
        $container = $containerBuilder->build();

        $recaptchaProphecy = $this->prophesize(ReCaptcha::class);
        $recaptchaProphecy->verify('good response', '1.2.3.4')
            ->willReturn(new \ReCaptcha\Response(true));
        $recaptchaProphecy->verify('bad response', '1.2.3.4')
            ->willReturn(new \ReCaptcha\Response(false));
        // Blank is mocked succeeding so that the deserialise error unit test behaves
        // as it did before we had captcha verification.
        $recaptchaProphecy->verify('', '1.2.3.4')
            ->willReturn(new \ReCaptcha\Response(true));
        $container->set(ReCaptcha::class, $recaptchaProphecy->reveal());

        // For tests, we need to stub out Redis so that rate limiting middleware doesn't
        // crash trying to actually connect to REDIS_HOST "dummy-redis-hostname". (We also
        // don't want tests depending upon *real* Redis.)
        $redisProphecy = $this->prophesize(Redis::class);
        $redisProphecy->isConnected()->willReturn(true);
        $redisProphecy->mget(['identity-test:10d49f663215e991d10df22692f03e89'])->willReturn(null);
        $redisProphecy->mget(['identity-test:BigGive__Identity__Domain__Person__CLASSMETADATA__'])->wilLReturn(null);
        // symfony/cache Redis adapter apparently does something around prepping value-setting
        // through a fancy pipeline() and calls this.
        $redisProphecy->multi(Argument::any())->willReturn();
        // Accept cache bits trying to set *anything* on the mocked Redis. We don't list exact calls
        // because this will include every bit of frequently-changing class metadata that Doctrine
        // caches, amongst other things.
        $redisProphecy
            ->setex(Argument::type('string'), 3600, Argument::type('string'))
            ->willReturn(true);
        $redisProphecy->exec()->willReturn(); // Commits the multi() operation.
        $container->set(Redis::class, $redisProphecy->reveal());

        // Instantiate the app
        AppFactory::setContainer($container);
        $app = AppFactory::create();

        // Register routes
        $routes = require __DIR__ . '/../app/routes.php';
        $routes($app);

        return $app;
    }

    /**
     * @param string $method
     * @param string $path
     * @param array  $headers
     * @param array  $cookies
     * @param array  $serverParams
     * @return ServerRequestInterface
     */
    protected function createRequest(
        string $method,
        string $path,
        array $headers = [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X-Forwarded-For' => '1.2.3.4', // Simulate ALB in unit tests by default.
        ],
        array $cookies = [],
        array $serverParams = []
    ): ServerRequestInterface {
        $uri = new Uri('', '', 80, $path);
        $handle = fopen('php://temp', 'wb+');
        $stream = (new StreamFactory())->createStreamFromResource($handle);

        $h = new Headers();
        foreach ($headers as $name => $value) {
            $h->addHeader($name, $value);
        }

        return new SlimRequest($method, $uri, $h, $cookies, $serverParams, $stream);
    }
}
