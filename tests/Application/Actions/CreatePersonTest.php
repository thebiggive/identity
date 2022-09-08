<?php

declare(strict_types=1);

namespace BigGive\Identity\Tests\Application\Actions;

use BigGive\Identity\Application\Actions\CreatePerson;
use BigGive\Identity\Client\BadRequestException;
use BigGive\Identity\Client\Mailer;
use BigGive\Identity\Domain\Person;
use BigGive\Identity\Repository\PersonRepository;
use BigGive\Identity\Tests\TestCase;
use BigGive\Identity\Tests\TestPeopleTrait;
use Doctrine\ORM\EntityManagerInterface;
use Prophecy\Argument;
use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\Uuid;
use Slim\Exception\HttpUnauthorizedException;

class CreatePersonTest extends TestCase
{
    use TestPeopleTrait;

    public function testSuccess(): void
    {
        $person = $this->getTestPerson();

        $personWithPostPersistData = clone $person;
        $personWithPostPersistData->setId(Uuid::fromString('12345678-1234-1234-1234-1234567890ab'));
        // Call same create/update time initialisers as lifecycle hooks
        $personWithPostPersistData->createdNow();

        $app = $this->getAppInstance();

        $personRepoProphecy = $this->prophesize(PersonRepository::class);
        $personRepoProphecy->persist(Argument::type(Person::class))
            ->shouldBeCalledOnce()
            ->willReturn($personWithPostPersistData);

        $mailerClientProphecy = $this->prophesize(Mailer::class);
        $mailerClientProphecy
            ->sendEmail($person->toMailerPayload())
            ->shouldBeCalledOnce()
            ->willReturn(true);

        $app->getContainer()->set(PersonRepository::class, $personRepoProphecy->reveal());
        $app->getContainer()->set(Mailer::class, $mailerClientProphecy->reveal());

        $request = $this->buildRequest([
            'first_name' => $person->first_name,
            'last_name' => $person->last_name,
            'raw_password' => $person->raw_password,
            'email_address' => $person->email_address,
            'captcha_code' => 'good response',
        ]);

        $response = $app->handle($request);
        $payloadJSON = (string) $response->getBody();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertJson($payloadJSON);

        $payload = json_decode($payloadJSON, false, 512, JSON_THROW_ON_ERROR);

        // Mocked PersonRepository sets a UUID in code.
        $this->assertIsString($payload->uuid);
        $this->assertSame(36, strlen($payload->uuid));

        $this->assertEquals('Loraine', $payload->first_name);
        $this->assertNotEmpty($payload->created_at);
        $this->assertNotEmpty($payload->updated_at);
    }

    public function testFailingCaptcha(): void
    {
        $this->expectException(HttpUnauthorizedException::class);
        $this->expectExceptionMessage('Unauthorised');

        $person = $this->getTestPerson();

        $app = $this->getAppInstance();

        $entityManagerProphecy = $this->prophesize(EntityManagerInterface::class);
        $entityManagerProphecy->persist(Argument::type(Person::class))->shouldNotBeCalled();
        $entityManagerProphecy->flush()->shouldNotBeCalled();

        $app->getContainer()->set(EntityManagerInterface::class, $entityManagerProphecy->reveal());

        $request = $this->buildRequest([
            'first_name' => $person->first_name,
            'last_name' => $person->last_name,
            'raw_password' => $person->raw_password,
            'email_address' => $person->email_address,
            'captcha_code' => 'bad response',
        ]);

        $app->handle($request);
    }

    public function testMissingCaptcha(): void
    {
        $person = $this->getTestPerson();

        $app = $this->getAppInstance();

        $personRepoProphecy = $this->prophesize(PersonRepository::class);
        $personRepoProphecy->persist(Argument::type(Person::class))
            ->shouldNotBeCalled();

        $app->getContainer()->set(PersonRepository::class, $personRepoProphecy->reveal());

        $request = $this->buildRequest([
            'first_name' => $person->first_name,
            'last_name' => $person->last_name,
            'raw_password' => $person->raw_password,
            'email_address' => $person->email_address,
        ]);

        $response = $app->handle($request);
        $payloadJSON = (string) $response->getBody();

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertJson($payloadJSON);

        $expectedJSON = json_encode([
            'error' => [
                'description' => 'Validation error: Captcha is required to create an account',
                'type' => 'BAD_REQUEST',
            ],
            'statusCode' => 400,
        ], JSON_THROW_ON_ERROR);
        $this->assertJsonStringEqualsJsonString($expectedJSON, $payloadJSON);
    }

    public function testMissingData(): void
    {
        $person = $this->getTestPerson();

        $app = $this->getAppInstance();

        $personRepoProphecy = $this->prophesize(PersonRepository::class);
        $personRepoProphecy->persist(Argument::type(Person::class))
            ->shouldNotBeCalled();

        $app->getContainer()->set(PersonRepository::class, $personRepoProphecy->reveal());

        $request = $this->buildRequest([
            'first_name' => $person->first_name,
            'captcha_code' => 'good response',
        ]);

        $response = $app->handle($request);
        $payloadJSON = (string) $response->getBody();

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertJson($payloadJSON);

        $expectedJSON = json_encode([
            'error' => [
                'description' => 'Validation error: Password of 10 or more characters' .
                ' is required to create an account; last_name must not be blank; email_address must not be blank',
                'type' => 'BAD_REQUEST',
            ],
            'statusCode' => 400,
        ], JSON_THROW_ON_ERROR);
        $this->assertJsonStringEqualsJsonString($expectedJSON, $payloadJSON);
    }

    public function testTooShortPassword(): void
    {
        $person = $this->getTestPerson();

        $app = $this->getAppInstance();

        $personRepoProphecy = $this->prophesize(PersonRepository::class);
        $personRepoProphecy->persist(Argument::type(Person::class))
            ->shouldNotBeCalled();

        $app->getContainer()->set(PersonRepository::class, $personRepoProphecy->reveal());

        $request = $this->buildRequest([
            'first_name' => $person->first_name,
            'last_name' => $person->last_name,
            'raw_password' => mb_substr($person->raw_password, 0, 9), // 1 below minimum 10 characters
            'email_address' => $person->email_address,
            'captcha_code' => 'good response',
        ]);

        $response = $app->handle($request);
        $payloadJSON = (string) $response->getBody();

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertJson($payloadJSON);

        $expectedJSON = json_encode([
            'error' => [
                'description' => 'Validation error: Password of 10 or more characters is required to create an account',
                'type' => 'BAD_REQUEST',
            ],
            'statusCode' => 400,
        ], JSON_THROW_ON_ERROR);
        $this->assertJsonStringEqualsJsonString($expectedJSON, $payloadJSON);
    }

    public function testBadJSON(): void
    {
        $app = $this->getAppInstance();

        $request = $this->buildRequestRaw('<');

        $response = $app->handle($request);
        $payloadJSON = (string) $response->getBody();

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertJson($payloadJSON);

        $expectedJSON = json_encode([
            'error' => [
                'description' => 'Person Create data deserialise error',
                'type' => 'BAD_REQUEST',
            ],
            'statusCode' => 400,
        ], JSON_THROW_ON_ERROR);
        $this->assertJsonStringEqualsJsonString($expectedJSON, $payloadJSON);
    }

    public function testFailedMailerCallout(): void
    {
        $person = $this->getTestPerson();

        $personWithPostPersistData = clone $person;
        $personWithPostPersistData->setId(Uuid::fromString('12345678-1234-1234-1234-1234567890ab'));
        // Call same create/update time initialisers as lifecycle hooks
        $personWithPostPersistData->createdNow();

        $app = $this->getAppInstance();

        $personRepoProphecy = $this->prophesize(PersonRepository::class);
        $personRepoProphecy->persist(Argument::type(Person::class))
            ->shouldBeCalledOnce()
            ->willReturn($personWithPostPersistData);

        $mailerClientProphecy = $this->prophesize(Mailer::class);
        $mailerClientProphecy
            ->sendEmail($person->toMailerPayload())
            ->shouldBeCalledOnce()
            ->willReturn(false);

        $app->getContainer()->set(PersonRepository::class, $personRepoProphecy->reveal());
        $app->getContainer()->set(Mailer::class, $mailerClientProphecy->reveal());

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Failed to send registration success email to newly registered donor.');

        $request = $this->buildRequest([
            'first_name' => $person->first_name,
            'last_name' => $person->last_name,
            'raw_password' => $person->raw_password,
            'email_address' => $person->email_address,
            'captcha_code' => 'good response',
        ]);

        // Simulate a POST /persons request
        $app->handle($request);
    }

    private function buildRequest(array $payloadValues): ServerRequestInterface
    {
        return $this->buildRequestRaw(json_encode($payloadValues, JSON_THROW_ON_ERROR));
    }

    private function buildRequestRaw(string $payloadLiteral): ServerRequestInterface
    {
        // Accept JSON is the `createRequest()` default.
        $request = $this->createRequest('POST', '/v1/people');
        $request->getBody()->write($payloadLiteral);

        return $request;
    }
}
