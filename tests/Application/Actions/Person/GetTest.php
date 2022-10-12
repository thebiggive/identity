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

class GetTest extends TestCase
{
    use TestPeopleTrait;

    public function testSuccessWithSpendableStripeBalances(): void
    {
        $person = $this->getInitialisedPerson(true);

        $app = $this->getAppInstance();

        $personRepoProphecy = $this->prophesize(PersonRepository::class);
        $personRepoProphecy->find(static::$testPersonUuid)
            ->shouldBeCalledOnce()
            ->willReturn($person);
        $personRepoProphecy->persist(Argument::type(Person::class))
            ->shouldNotBeCalled();

        $app->getContainer()->set(PersonRepository::class, $personRepoProphecy->reveal());
        $app->getContainer()->set(StripeClient::class, $this->getStripeClientWithMock('customer_usable_credit'));

        $response = $app->handle($this->buildRequest(static::$testPersonUuid));
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

        $this->assertObjectHasAttribute('cash_balance', $payload);
        $this->assertIsObject($payload->cash_balance);
        $this->assertEquals((object) [
            'eur' => 123,
            'gbp' => 55500,
        ], $payload->cash_balance);
    }

    public function testSuccessWithZeroStripeBalances(): void
    {
        $person = $this->getInitialisedPerson(true);

        $app = $this->getAppInstance();

        $personRepoProphecy = $this->prophesize(PersonRepository::class);
        $personRepoProphecy->find(static::$testPersonUuid)
            ->shouldBeCalledOnce()
            ->willReturn($person);
        $personRepoProphecy->persist(Argument::type(Person::class))
            ->shouldNotBeCalled();

        $app->getContainer()->set(PersonRepository::class, $personRepoProphecy->reveal());
        $app->getContainer()->set(StripeClient::class, $this->getStripeClientWithMock('customer_zero_credit'));

        $response = $app->handle($this->buildRequest(static::$testPersonUuid));
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

        $this->assertEmpty($payload->cash_balance);
    }

    public function testSuccessWithNoStripeBalances(): void
    {
        $person = $this->getInitialisedPerson(true);

        $app = $this->getAppInstance();

        $personRepoProphecy = $this->prophesize(PersonRepository::class);
        $personRepoProphecy->find(static::$testPersonUuid)
            ->shouldBeCalledOnce()
            ->willReturn($person);
        $personRepoProphecy->persist(Argument::type(Person::class))
            ->shouldNotBeCalled();

        $app->getContainer()->set(PersonRepository::class, $personRepoProphecy->reveal());
        $app->getContainer()->set(StripeClient::class, $this->getStripeClientWithMock('customer_no_credit'));

        $response = $app->handle($this->buildRequest(static::$testPersonUuid));
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

        $this->assertEmpty($payload->cash_balance);
    }

    public function testSuccessWithNonAutomaticallyReconcileStripeBalances(): void
    {
        $person = $this->getInitialisedPerson(true);

        $app = $this->getAppInstance();

        $personRepoProphecy = $this->prophesize(PersonRepository::class);
        $personRepoProphecy->find(static::$testPersonUuid)
            ->shouldBeCalledOnce()
            ->willReturn($person);
        $personRepoProphecy->persist(Argument::type(Person::class))
            ->shouldNotBeCalled();

        $app->getContainer()->set(PersonRepository::class, $personRepoProphecy->reveal());
        $app->getContainer()->set(StripeClient::class, $this->getStripeClientWithMock('customer_manual_only_credit'));

        $response = $app->handle($this->buildRequest(static::$testPersonUuid));
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

        $this->assertEmpty($payload->cash_balance);
    }

    public function testIncompleteAuthToken(): void
    {
        $this->expectException(HttpUnauthorizedException::class);
        $this->expectExceptionMessage('Unauthorised');

        $app = $this->getAppInstance();

        $personRepoProphecy = $this->prophesize(PersonRepository::class);
        $personRepoProphecy->find(static::$testPersonUuid)
            ->shouldNotBeCalled();
        $personRepoProphecy->persist(Argument::type(Person::class))
            ->shouldNotBeCalled();

        $app->getContainer()->set(PersonRepository::class, $personRepoProphecy->reveal());

        $request = $this->buildRequestRaw(static::$testPersonUuid)
            ->withHeader('x-tbg-auth', Token::create(static::$testPersonUuid, false, 'cus_aaaaaaaaaaaa11'));
        $app->handle($request);
    }

    public function testMissingAuthToken(): void
    {
        $this->expectException(HttpUnauthorizedException::class);
        $this->expectExceptionMessage('Unauthorised');

        $app = $this->getAppInstance();

        $personRepoProphecy = $this->prophesize(PersonRepository::class);
        $personRepoProphecy->find(static::$testPersonUuid)
            ->shouldNotBeCalled();
        $personRepoProphecy->persist(Argument::type(Person::class))
            ->shouldNotBeCalled();

        $app->getContainer()->set(PersonRepository::class, $personRepoProphecy->reveal());

        $request = $this->buildRequestRaw(static::$testPersonUuid);

        $app->handle($request);
    }

    private function buildRequest(string $personId): ServerRequestInterface
    {
        return $this->buildRequestRaw($personId)
            ->withHeader('x-tbg-auth', Token::create(static::$testPersonUuid, true, 'cus_aaaaaaaaaaaa11'));
    }

    private function buildRequestRaw(string $personId): ServerRequestInterface
    {
        // Accept JSON is the `createRequest()` default.
        return $this->createRequest('GET', '/v1/people/' . $personId);
    }

    private function getStripeClientWithMock(string $mockName): StripeClient
    {
        $mockResponse = json_decode(
            file_get_contents(dirname(__DIR__, 3) . '/MockStripeResponses/' . $mockName . '.json'),
            false,
            512,
            JSON_THROW_ON_ERROR,
        );

        $stripeCustomersProphecy = $this->prophesize(CustomerService::class);
        $stripeCustomersProphecy->retrieve(static::$testPersonStripeCustomerId, ['expand' => ['cash_balance']])
            ->shouldBeCalledOnce()
            ->willReturn($mockResponse);

        $stripeClientProphecy = $this->prophesize(StripeClient::class);
        $stripeClientProphecy->customers = $stripeCustomersProphecy->reveal();

        return $stripeClientProphecy->reveal();
    }
}
