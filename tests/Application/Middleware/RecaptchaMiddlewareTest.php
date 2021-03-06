<?php

declare(strict_types=1);

namespace BigGive\Identity\Tests\Application\Middleware;

use BigGive\Identity\Application\Middleware\RecaptchaMiddleware;
use BigGive\Identity\Tests\TestCase;
use BigGive\Identity\Tests\TestPeopleTrait;
use Psr\Log\LoggerInterface;
use ReCaptcha\ReCaptcha;
use Slim\App;
use Slim\CallableResolver;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Response;
use Slim\Routing\Route;
use Slim\Exception\HttpUnauthorizedException;
use Symfony\Component\Serializer\SerializerInterface;

class RecaptchaMiddlewareTest extends TestCase
{
    use TestPeopleTrait;

    public function testFailure(): void
    {
        $personObject = $this->getTestPerson();
        $person = $personObject->jsonSerialize();
        $person['captcha_code'] = 'bad response';
        $body = json_encode($person);

        $request = $this->createRequest('POST', '/v1/people');
        $request->getBody()->write($body);

        $this->expectException(HttpUnauthorizedException::class);
        $this->expectExceptionMessage('Unauthorised');

        // Because the 401 ends the request, we can dispatch this against realistic, full app
        // middleware and test this piece of middleware in the process.
        $response = $this->getAppInstance()
            ->getMiddlewareDispatcher()
            ->handle($request);
    }

    public function testSuccess(): void
    {
        $personObject = $this->getTestPerson();
        $person = $personObject->jsonSerialize();
        $person['captcha_code'] = 'good response';
        $body = json_encode($person);

        $request = $this->createRequest('POST', '/v1/people')
            // Because we're only running the single middleware and not the app stack, we need
            // to set this attribute manually to simulate what ClientIp middleware does on real
            // runs.
            ->withAttribute('client-ip', '1.2.3.4');
        $request->getBody()->write($body);

        $container = $this->getAppInstance()->getContainer();

        // For the success case we can't fully handle the request without covering a lot of stuff
        // outside the middleware, since that would mean creating a Person and so mocking DB bits
        // etc. So unlike for failure, we create an isolated middleware object to invoke.

        $middleware = new RecaptchaMiddleware(
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
            '/v1/people',
            static function () {
                return new Response(200);
            },
            new ResponseFactory(),
            new CallableResolver()
        );
    }
}
