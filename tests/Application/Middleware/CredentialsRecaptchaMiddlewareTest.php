<?php

declare(strict_types=1);

namespace BigGive\Identity\Tests\Application\Middleware;

use BigGive\Identity\Application\Middleware\CredentialsRecaptchaMiddleware;
use BigGive\Identity\Domain\Credentials;
use BigGive\Identity\Tests\TestCase;
use Psr\Log\LoggerInterface;
use ReCaptcha\ReCaptcha;
use Slim\CallableResolver;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Response;
use Slim\Routing\Route;
use Slim\Exception\HttpUnauthorizedException;
use Symfony\Component\Serializer\SerializerInterface;

class CredentialsRecaptchaMiddlewareTest extends TestCase
{
    public function testFailure(): void
    {
        $serializer = $this->getAppInstance()->getContainer()->get(SerializerInterface::class);

        $credentialsObject = $this->getTestCredentials();

        $credentialsSerialised = $serializer->serialize($credentialsObject, 'json');
        $credentials = json_decode($credentialsSerialised, true, 512, JSON_THROW_ON_ERROR);
        $credentials['captcha_code'] = 'bad response';
        $body = json_encode($credentials);

        $request = $this->createRequest('POST', '/v1/auth');
        $request->getBody()->write($body);

        $this->expectException(HttpUnauthorizedException::class);
        $this->expectExceptionMessage('Unauthorised');

        // Because the 401 ends the request, we can dispatch this against realistic, full app
        // middleware and test this piece of middleware in the process.
        $this->getAppInstance()
            ->getMiddlewareDispatcher()
            ->handle($request);
    }

    public function testSuccess(): void
    {
        $serializer = $this->getAppInstance()->getContainer()->get(SerializerInterface::class);

        $credentialsObject = $this->getTestCredentials();

        $credentialsSerialised = $serializer->serialize($credentialsObject, 'json');
        $credentials = json_decode($credentialsSerialised, true, 512, JSON_THROW_ON_ERROR);
        $credentials['captcha_code'] = 'good response';
        $body = json_encode($credentials);

        $request = $this->createRequest('POST', '/v1/auth')
            // Because we're only running the single middleware and not the app stack, we need
            // to set this attribute manually to simulate what ClientIp middleware does on real
            // runs.
            ->withAttribute('client-ip', '1.2.3.4');
        $request->getBody()->write($body);

        $container = $this->getAppInstance()->getContainer();

        // For the success case we can't fully handle the request without covering a lot of stuff
        // outside the middleware, which is covered in `LoginTest`'s end to end action test already.
        // So we are better creating an isolated middleware object to invoke.
        $middleware = new CredentialsRecaptchaMiddleware(
            $container->get(LoggerInterface::class), // null logger already set up
            $container->get(ReCaptcha::class), // already mocked with success simulation
            $container->get(SerializerInterface::class),
        );
        $response = $middleware->process($request, $this->getSuccessHandler());

        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * Simulate a route returning a 200 OK. Test methods should get here only when they expect auth
     * success from the middleware.
     */
    private function getSuccessHandler(): Route
    {
        return new Route(
            ['POST'],
            '/v1/auth',
            static function () {
                return new Response(200);
            },
            new ResponseFactory(),
            new CallableResolver()
        );
    }

    private function getTestCredentials(): Credentials
    {
        $credentials = new Credentials();
        $credentials->email_address = 'noel@example.com';
        $credentials->raw_password = 'mySecurePassword123';

        return $credentials;
    }
}
