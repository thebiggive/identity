<?php

declare(strict_types=1);

namespace BigGive\Identity\Tests\Application\Actions\Person;

use BigGive\Identity\Application\Actions\Person\Update;
use BigGive\Identity\Application\Auth\Token;
use BigGive\Identity\Domain\Person;
use BigGive\Identity\Repository\PersonRepository;
use BigGive\Identity\Tests\TestCase;
use BigGive\Identity\Tests\TestPeopleTrait;
use BigGive\Identity\Tests\TestPromises\SucceedThenThrowWithDuplicateEmailPromise;
use Prophecy\Argument;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpUnauthorizedException;
use Stripe\Service\CustomerService;
use Stripe\StripeClient;
use Symfony\Component\Uid\Uuid;

class UpdateTest extends TestCase
{
    use TestPeopleTrait;

    /**
     * Includes confirming that setting a Stripe Customer ID too is ignored.
     */
    public function testSuccessSettingPassword(): void
    {
        // Start without password.
        $person = $this->getTestPerson(false, false);

        $app = $this->getAppInstance();

        $personRepoProphecy = $this->prophesize(PersonRepository::class);
        $personRepoProphecy->find(static::$testPersonUuid)
            ->shouldBeCalledOnce()
            ->willReturn($person);

        // It's important we are prescriptive about the most essential properties of the mock-persisted
        // object in at least 1 test. Otherwise we can end up with bugs like ID-30 where properties
        // are set unexpectedly but unit tests do not surface a problem.
        /** @var Argument\Token\CallbackToken $personToken */
        $personToken = Argument::that(function ($actual) {
            return $actual instanceof Person && $actual->stripe_customer_id === static::$testPersonStripeCustomerId;
        });

        $personRepoProphecy->persist($personToken)
            ->shouldBeCalledOnce()
            ->will(/**
             * @param array<Person> $args
             */ fn(array $args) => UpdateTest::initialisePerson($args[0], true));
        $personRepoProphecy->sendRegisteredEmail(Argument::type(Person::class))
            ->shouldBeCalledOnce()
            ->willReturn(true);

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
            // The requirements of `$personToken` implicitly validate that we are not passing
            // this fake value to the EntityManager's `persist(...)`.
            'stripe_customer_id' => 'cus_MyFakeId',
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

        // Validate that the response contains the original, not the fake overridden, Stripe
        // customer ID.
        $this->assertEquals($payload->stripe_customer_id, static::$testPersonStripeCustomerId);
    }

    public function testSettingPasswordForASecondPersonWithSameEmailAddress(): void
    {
        // Start without password.
        $person = $this->getTestPerson(false, false);
        // Come back from EM with password.
        $personWithPostPersistData = $this->getInitialisedPerson(true);

        $newUuid = Uuid::v4();
        $newStripeCustomerId = 'cus_aaaaaaaaaaaa12';
        $person2 = clone $personWithPostPersistData;
        $person2->setId($newUuid);
        $person2->stripe_customer_id = $newStripeCustomerId;

        $app = $this->getAppInstance();

        $personRepoProphecy = $this->prophesize(PersonRepository::class);
        $personRepoProphecy->find(static::$testPersonUuid)
            ->shouldBeCalledOnce()
            ->willReturn($person);
        $personRepoProphecy->find($newUuid)
            ->shouldBeCalledOnce()
            ->willReturn($person2);
        $personRepoProphecy->persist(Argument::type(Person::class))
            ->shouldBeCalledTimes(2)
            ->will(new SucceedThenThrowWithDuplicateEmailPromise(true));
        $personRepoProphecy->sendRegisteredEmail(Argument::type(Person::class))
            ->shouldBeCalledOnce()
            ->willReturn(true);

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
        // Expected failure is before we push to Stripe so no call with this ID expected.
        $stripeCustomersProphecy->update($newStripeCustomerId, Argument::type('array'))
            ->shouldNotBeCalled();
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

        $this->assertEquals(200, $response->getStatusCode());
        // Don't assert more stuff about the response because so far it's identical to
        // `testSuccessSettingPassword()`.

        // Now doing it again for a new Person should fail.
        $request2 = $this->buildRequestRaw((string) $newUuid, json_encode([
            'first_name' => $person2->first_name,
            'last_name' => $person2->last_name,
            'raw_password' => $person2->raw_password,
            'email_address' => $person2->email_address,
            'stripe_customer_id' => $newStripeCustomerId,
            'captcha_code' => 'good response',
        ], JSON_THROW_ON_ERROR))
            ->withHeader('x-tbg-auth', Token::create((string) $newUuid, false, $newStripeCustomerId));

        $responseFromSecondUpdate = $app->handle($request2);
        $this->assertEquals(400, $responseFromSecondUpdate->getStatusCode());

        $expectedJSON = json_encode([
            'error' => [
                'description' =>
                    'Your password could not be set. There is already a password set for your email address.',
                'type' => 'DUPLICATE_EMAIL_ADDRESS_WITH_PASSWORD',
            ],
        ], JSON_THROW_ON_ERROR);
        $this->assertJsonStringEqualsJsonString($expectedJSON, (string) $responseFromSecondUpdate->getBody());
    }

    public function testSettingPasswordWithFailedMailerCallout(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to send registration success email');

        // As above.
        $person = $this->getTestPerson(false, false);

        $app = $this->getAppInstance();

        $personRepoProphecy = $this->prophesize(PersonRepository::class);
        $personRepoProphecy->find(static::$testPersonUuid)
            ->shouldBeCalledOnce()
            ->willReturn($person);
        $personRepoProphecy->persist(Argument::type(Person::class))
            ->shouldBeCalledOnce()
            ->will(/**
             * @param array<Person> $args
             */ fn(array $args) => UpdateTest::initialisePerson($args[0], true));
        $personRepoProphecy->sendRegisteredEmail(Argument::type(Person::class))
            ->shouldBeCalledOnce()
            ->willReturn(false);

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

        $app->handle($request);
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
            ->shouldBeCalledOnce();

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
                'description' => 'last_name must not be blank; email_address must not be blank',
                'type' => 'BAD_REQUEST',
            ],
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
                'description' => 'Your password could not be set. Please ensure you chose one with at least 10 characters.',
                'type' => 'BAD_REQUEST',
            ],
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
