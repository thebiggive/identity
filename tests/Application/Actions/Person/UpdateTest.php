<?php

declare(strict_types=1);

namespace BigGive\Identity\Tests\Application\Actions\Person;

use BigGive\Identity\Application\Auth\Token;
use BigGive\Identity\Domain\Person;
use BigGive\Identity\Repository\PersonRepository;
use BigGive\Identity\Tests\TestCase;
use BigGive\Identity\Tests\TestPeopleTrait;
use Prophecy\Argument;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpUnauthorizedException;
use Stripe\Service\CustomerService;
use Stripe\StripeClient;

class UpdateTest extends TestCase
{
    use TestPeopleTrait;

    public function testSuccessSettingPassword(): void
    {
        $person = $this->getTestPerson();
        $personWithPostPersistData = $this->getInitialisedPerson(true);

        $app = $this->getAppInstance();

        $personRepoProphecy = $this->prophesize(PersonRepository::class);
        $personRepoProphecy->find(static::$testPersonUuid)
            ->shouldBeCalledOnce()
            ->willReturn($personWithPostPersistData);
        $personRepoProphecy->persist(Argument::type(Person::class))
            ->shouldBeCalledOnce()
            ->willReturn($personWithPostPersistData);

        // We don't use the returned properties so they're omitted for now, but in reality
        // `name` etc. will be set.
        $customerMockResult = (object) [
            'id' => static::$testPersonStripeCustomerId,
            'object' => 'customer',
        ];
        $stripeCustomersProphecy = $this->prophesize(CustomerService::class);
        $stripeCustomersProphecy->update(static::$testPersonStripeCustomerId, Argument::type('array'))
            ->willReturn($customerMockResult)
            ->shouldBeCalledOnce();
        $stripeClientProphecy = $this->prophesize(StripeClient::class);
        $stripeClientProphecy->customers = $stripeCustomersProphecy->reveal();

        $app->getContainer()->set(PersonRepository::class, $personRepoProphecy->reveal());
        $app->getContainer()->set(StripeClient::class, $stripeClientProphecy->reveal());

        $request = $this->buildRequest(static::$testPersonUuid, [
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

        // Mocked PersonRepsoitory sets a UUID in code.
        $this->assertSame(36, strlen((string) $payload->id));

        $this->assertEquals('Loraine', $payload->first_name);
        $this->assertNotEmpty($payload->created_at);

        $this->assertNotEmpty($payload->updated_at);
        $this->assertIsString($payload->updated_at);
        $this->assertTrue(new \DateTime($payload->updated_at) <= new \DateTime());
        $this->assertTrue(new \DateTime($payload->updated_at) >= (new \DateTime())->sub(new \DateInterval('PT5S')));

        $this->assertTrue($payload->has_password);
        // These should be unset by `HasPasswordNormalizer`.
        $this->assertObjectNotHasAttribute('raw_password', $payload);
        $this->assertObjectNotHasAttribute('password', $payload);
    }

    public function testSuccessSettingOnlyPersonInfo(): void
    {
        $person = $this->getTestPerson(true, false);
        $personWithPostPersistData = $this->getInitialisedPerson(false);

        $app = $this->getAppInstance();

        $personRepoProphecy = $this->prophesize(PersonRepository::class);
        $personRepoProphecy->find(static::$testPersonUuid)
            ->shouldBeCalledOnce()
            ->willReturn($personWithPostPersistData);
        $personRepoProphecy->persist(Argument::type(Person::class))
            ->shouldBeCalledOnce()
            ->willReturn($personWithPostPersistData);

        // We don't use the returned properties so they're omitted for now, but in reality
        // `name` etc. will be set.
        $customerMockResult = (object) [
            'id' => static::$testPersonStripeCustomerId,
            'object' => 'customer',
        ];
        $stripeCustomersProphecy = $this->prophesize(CustomerService::class);
        $stripeCustomersProphecy->update(static::$testPersonStripeCustomerId, Argument::type('array'))
            ->willReturn($customerMockResult)
            ->shouldBeCalledOnce();
        $stripeClientProphecy = $this->prophesize(StripeClient::class);
        $stripeClientProphecy->customers = $stripeCustomersProphecy->reveal();

        $app->getContainer()->set(PersonRepository::class, $personRepoProphecy->reveal());
        $app->getContainer()->set(StripeClient::class, $stripeClientProphecy->reveal());

        $request = $this->buildRequest(static::$testPersonUuid, [
            'first_name' => $person->first_name,
            'last_name' => $person->last_name,
            'email_address' => $person->email_address,
            'captcha_code' => 'good response',
        ]);

        $response = $app->handle($request);
        $payloadJSON = (string) $response->getBody();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertJson($payloadJSON);

        $payload = json_decode($payloadJSON, false, 512, JSON_THROW_ON_ERROR);

        // Mocked PersonRepsoitory sets a UUID in code.
        $this->assertSame(36, strlen((string) $payload->id));

        $this->assertEquals('Loraine', $payload->first_name);
        $this->assertNotEmpty($payload->created_at);

        $this->assertNotEmpty($payload->updated_at);
        $this->assertIsString($payload->updated_at);
        $this->assertTrue(new \DateTime($payload->updated_at) <= new \DateTime());
        $this->assertTrue(new \DateTime($payload->updated_at) >= (new \DateTime())->sub(new \DateInterval('PT5S')));

        $this->assertFalse($payload->has_password);
        $this->assertObjectNotHasAttribute('raw_password', $payload);
        $this->assertObjectNotHasAttribute('password', $payload);
    }

    public function testMissingData(): void
    {
        // Remove an existing property so test's validation fails even with Symfony serializer
        // loading in the existing ORM object's data.
        $person = $this->getTestPerson();
        $person->email_address = null;
        $person->last_name = null;

        $personFromORM = $this->getInitialisedPerson(false);
        $personFromORM->email_address = null;
        $personFromORM->last_name = null;

        $app = $this->getAppInstance();

        $personRepoProphecy = $this->prophesize(PersonRepository::class);
        $personRepoProphecy->find(static::$testPersonUuid)
            ->shouldBeCalledOnce()
            ->willReturn($personFromORM);
        $personRepoProphecy->persist(Argument::type(Person::class))
            ->shouldNotBeCalled();

        $app->getContainer()->set(PersonRepository::class, $personRepoProphecy->reveal());

        $request = $this->buildRequest(static::$testPersonUuid, [
            'first_name' => $person->first_name,
            'captcha_code' => 'good response',
        ]);

        $response = $app->handle($request);
        $payloadJSON = (string) $response->getBody();

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertJson($payloadJSON);

        $expectedJSON = json_encode([
            'error' => [
                'description' => 'Validation error: last_name must not be blank; email_address must not be blank',
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
        $personRepoProphecy->find(static::$testPersonUuid)
            ->shouldBeCalledOnce()
            ->willReturn($this->getInitialisedPerson(false));
        $personRepoProphecy->persist(Argument::type(Person::class))
            ->shouldNotBeCalled();

        $app->getContainer()->set(PersonRepository::class, $personRepoProphecy->reveal());

        $request = $this->buildRequest(static::$testPersonUuid, [
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
                'description' => 'Validation error: Password must be 10 or more characters',
                'type' => 'BAD_REQUEST',
            ],
            'statusCode' => 400,
        ], JSON_THROW_ON_ERROR);
        $this->assertJsonStringEqualsJsonString($expectedJSON, $payloadJSON);
    }

    public function testMissingAuthToken(): void
    {
        $this->expectException(HttpUnauthorizedException::class);
        $this->expectExceptionMessage('Unauthorised');

        $person = $this->getTestPerson();

        $app = $this->getAppInstance();

        $personRepoProphecy = $this->prophesize(PersonRepository::class);
        $personRepoProphecy->find(static::$testPersonUuid)
            ->shouldNotBeCalled();
        $personRepoProphecy->persist(Argument::type(Person::class))
            ->shouldNotBeCalled();

        $stripeCustomersProphecy = $this->prophesize(CustomerService::class);
        $stripeCustomersProphecy->update(static::$testPersonStripeCustomerId, Argument::type('array'))
            ->shouldNotBeCalled();
        $stripeClientProphecy = $this->prophesize(StripeClient::class);
        $stripeClientProphecy->customers = $stripeCustomersProphecy->reveal();

        $app->getContainer()->set(PersonRepository::class, $personRepoProphecy->reveal());
        $app->getContainer()->set(StripeClient::class, $stripeClientProphecy->reveal());

        $request = $this->buildRequestRaw(static::$testPersonUuid, json_encode([
            'first_name' => $person->first_name,
            'last_name' => $person->last_name,
            'email_address' => $person->email_address,
            'captcha_code' => 'good response',
        ], JSON_THROW_ON_ERROR));

        $app->handle($request);
    }

    public function testBadJSON(): void
    {
        $app = $this->getAppInstance();

        $personRepoProphecy = $this->prophesize(PersonRepository::class);
        $personRepoProphecy->find(static::$testPersonUuid)
            ->shouldBeCalledOnce()
            ->willReturn($this->getInitialisedPerson(false));
        $personRepoProphecy->persist(Argument::type(Person::class))
            ->shouldNotBeCalled();

        $app->getContainer()->set(PersonRepository::class, $personRepoProphecy->reveal());

        $request = $this->buildRequestRaw(static::$testPersonUuid, '<')
            ->withHeader('x-tbg-auth', Token::create(static::$testPersonUuid, false, 'cus_aaaaaaaaaaaa11'));

        $response = $app->handle($request);
        $payloadJSON = (string) $response->getBody();

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertJson($payloadJSON);

        $expectedJSON = json_encode([
            'error' => [
                'description' => 'Person Update data deserialise error',
                'type' => 'BAD_REQUEST',
            ],
            'statusCode' => 400,
        ], JSON_THROW_ON_ERROR);
        $this->assertJsonStringEqualsJsonString($expectedJSON, $payloadJSON);
    }

    private function buildRequest(string $personId, array $payloadValues): ServerRequestInterface
    {
        return $this->buildRequestRaw($personId, json_encode($payloadValues, JSON_THROW_ON_ERROR))
            ->withHeader('x-tbg-auth', Token::create(static::$testPersonUuid, false, 'cus_aaaaaaaaaaaa11'));
    }

    private function buildRequestRaw(string $personId, string $payloadLiteral): ServerRequestInterface
    {
        // Accept JSON is the `createRequest()` default.
        $request = $this->createRequest('PUT', '/v1/people/' . $personId);
        $request->getBody()->write($payloadLiteral);

        return $request;
    }
}
