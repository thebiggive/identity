<?php

declare(strict_types=1);

namespace BigGive\Identity\Tests\Application\Actions;

use BigGive\Identity\Application\Security\AuthenticationException;
use BigGive\Identity\Application\Security\Password;
use BigGive\Identity\Domain\Person;
use BigGive\Identity\Repository\PersonRepository;
use BigGive\Identity\Tests\TestCase;
use BigGive\Identity\Tests\TestPeopleTrait;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpUnauthorizedException;

class LoginTest extends TestCase
{
    use TestPeopleTrait;

    public function testSuccess(): void
    {
        $person = $this->getTestPerson(true);

        $app = $this->getAppInstance();

        $personWithHashMatchingRawPasswordFromLoginObject = $this->prophesize(Person::class);
        $personWithHashMatchingRawPasswordFromLoginObject->getPasswordHash()
            ->shouldBeCalledOnce()
            ->willReturn(Password::hash($person->raw_password));
        $personWithHashMatchingRawPasswordFromLoginObject->getId()
            ->shouldBeCalledOnce()
            ->willReturn($person->getId());

        $personRepoProphecy = $this->prophesize(PersonRepository::class);
        $personRepoProphecy->findPersonByEmailAddress($person->email_address)
            ->shouldBeCalledOnce()
            ->willReturn($personWithHashMatchingRawPasswordFromLoginObject);

        $app->getContainer()->set(PersonRepository::class, $personRepoProphecy->reveal());

        $request = $this->buildRequest([
            'captcha_code' => 'good response',
            'email_address' => $person->email_address,
            'raw_password' => $person->raw_password,
        ]);

        $response = $app->handle($request);
        $payloadJSON = (string) $response->getBody();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertJson($payloadJSON);

        $payload = json_decode($payloadJSON, false, 512, JSON_THROW_ON_ERROR);

        $this->assertIsString($payload->jwt);
        $this->assertIsString($payload->id);
        $this->assertNotEmpty($payload->jwt);
        $this->assertNotEmpty($payload->id);
    }

    public function testNoUserFound(): void
    {
        $person = $this->getTestPerson();

        $app = $this->getAppInstance();

        $personRepoProphecy = $this->prophesize(PersonRepository::class);
        $personRepoProphecy->findPersonByEmailAddress($person->email_address)
            ->shouldBeCalledOnce()
            ->willReturn(null);

        $app->getContainer()->set(PersonRepository::class, $personRepoProphecy->reveal());

        $request = $this->buildRequest([
            'captcha_code' => 'good response',
            'email_address' => $person->email_address,
            'raw_password' => 'notThisOne',
        ]);

        $response = $app->handle($request);
        $payloadJSON = (string) $response->getBody();

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertJson($payloadJSON);

        $expectedJSON = json_encode([
            'error' => [
                'description' => 'Invalid credentials',
                'type' => 'VALIDATION_ERROR',
            ],
            'statusCode' => 401,
        ], JSON_THROW_ON_ERROR);
        $this->assertJsonStringEqualsJsonString($expectedJSON, $payloadJSON);
    }

    public function testIncorrectPassword(): void
    {
        $person = $this->getTestPerson();

        $app = $this->getAppInstance();

        $personWithHashNotMatchingRawPassword = $this->prophesize(Person::class);
        $personWithHashNotMatchingRawPassword->getPasswordHash()
            ->shouldBeCalledOnce()
            ->willReturn('$2y$10$someOtherHash');

        $personRepoProphecy = $this->prophesize(PersonRepository::class);
        $personRepoProphecy->findPersonByEmailAddress($person->email_address)
            ->shouldBeCalledOnce()
            ->willReturn($personWithHashNotMatchingRawPassword);

        $app->getContainer()->set(PersonRepository::class, $personRepoProphecy->reveal());

        $request = $this->buildRequest([
            'captcha_code' => 'good response',
            'email_address' => $person->email_address,
            'raw_password' => 'notThisOne',
        ]);

        $response = $app->handle($request);
        $payloadJSON = (string) $response->getBody();

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertJson($payloadJSON);

        $expectedJSON = json_encode([
            'error' => [
                'description' => 'Invalid credentials',
                'type' => 'VALIDATION_ERROR',
            ],
            'statusCode' => 401,
        ], JSON_THROW_ON_ERROR);
        $this->assertJsonStringEqualsJsonString($expectedJSON, $payloadJSON);
    }

    public function testFailingCaptcha(): void
    {
        $this->expectException(HttpUnauthorizedException::class);
        $this->expectExceptionMessage('Unauthorised');

        $person = $this->getTestPerson();

        $app = $this->getAppInstance();

        $personWithHashNotMatchingRawPassword = $this->prophesize(Person::class);
        $personWithHashNotMatchingRawPassword->getPasswordHash()
            ->shouldNotBeCalled();

        $personRepoProphecy = $this->prophesize(PersonRepository::class);
        $personRepoProphecy->findPersonByEmailAddress($person->email_address)
            ->shouldNotBeCalled();

        $app->getContainer()->set(PersonRepository::class, $personRepoProphecy->reveal());

        $request = $this->buildRequest([
            'captcha_code' => 'bad response',
            'email_address' => $person->email_address,
            'raw_password' => 'notThisOne',
        ]);

        $app->handle($request);
    }

    private function buildRequest(array $payload): ServerRequestInterface
    {
        // Accept JSON is the `createRequest()` default.
        $request = $this->createRequest('POST', '/v1/auth');
        $request->getBody()->write(json_encode(
            $payload,
            JSON_THROW_ON_ERROR,
        ));

        return $request;
    }
}
