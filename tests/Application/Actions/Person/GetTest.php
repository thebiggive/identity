<?php

declare(strict_types=1);

namespace BigGive\Identity\Tests\Application\Actions\Person;

use BigGive\Identity\Application\Auth\Token;
use BigGive\Identity\Client\Stripe;
use BigGive\Identity\Domain\Person;
use BigGive\Identity\Repository\PersonRepository;
use BigGive\Identity\Tests\StripeFormatting;
use BigGive\Identity\Tests\TestCase;
use BigGive\Identity\Tests\TestPeopleTrait;
use DI\Container;
use Prophecy\Argument;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpUnauthorizedException;
use Stripe\Service\CustomerService;
use Stripe\Service\PaymentIntentService;
use Stripe\StripeObject;

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
        $app->getContainer()->set(Stripe::class, $this->getStripeClientWithMock('customer_usable_credit'));

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
        $app->getContainer()->set(Stripe::class, $this->getStripeClientWithMock('customer_zero_credit'));

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

        $this->assertIsObject($payload->cash_balance);
        $this->assertObjectNotHasAttribute('gbp', $payload->cash_balance);
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
        $app->getContainer()->set(Stripe::class, $this->getStripeClientWithMock('customer_no_credit'));

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

        $this->assertIsObject($payload->cash_balance);
        $this->assertObjectNotHasAttribute('gbp', $payload->cash_balance);
    }

    public function testSuccessWithPendingTipAndNoBalances(): void
    {
        $person = $this->getInitialisedPerson(true);

        $app = $this->getAppInstance();
        /** @var Container $container */
        $container = $app->getContainer();

        $personRepoProphecy = $this->prophesize(PersonRepository::class);
        $personRepoProphecy->find(self::$testPersonUuid)
            ->shouldBeCalledOnce()
            ->willReturn($person);
        $personRepoProphecy->persist(Argument::type(Person::class))
            ->shouldNotBeCalled();

        $container->set(PersonRepository::class, $personRepoProphecy->reveal());
        $container->set(
            Stripe::class,
            $this->getStripeClientWithMock(
                mockName: 'customer_no_credit',
                // List of payment intents for the test customer will be 1x £1k customer_balance
                // funded donation (tip) with metadata.campaignName = 'Big Give General Donations'.
                piMockName: 'pi_list_one_pending_customer_balance_tip',
            )
        );

        $response = $app->handle($this->buildRequest(
            self::$testPersonUuid,
            withTipBalance: true,
        ));
        $payloadJSON = (string) $response->getBody();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertJson($payloadJSON);

        /** @var object{cash_balance: mixed, pending_tip_balance: mixed} */
        $payload = json_decode($payloadJSON, false, 512, JSON_THROW_ON_ERROR);

        $this->assertIsObject($payload->cash_balance);
        $this->assertObjectNotHasAttribute('gbp', $payload->cash_balance);

        $this->assertObjectHasAttribute('pending_tip_balance', $payload);
        $this->assertIsObject($payload->pending_tip_balance);
        $this->assertEquals((object) [
            'gbp' => 1_000_00, // £1,000 tip (per mock response derived from local tests)
        ], $payload->pending_tip_balance);
    }

    public function testSuccessWithNonAutomaticallyReconciledStripeBalances(): void
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
        $app->getContainer()->set(Stripe::class, $this->getStripeClientWithMock('customer_manual_only_credit'));

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

        $this->assertIsObject($payload->cash_balance);
        $this->assertObjectNotHasAttribute('eur', $payload->cash_balance);
        $this->assertObjectNotHasAttribute('gbp', $payload->cash_balance);
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

    private function buildRequest(string $personId, bool $withTipBalance = false): ServerRequestInterface
    {
        return $this->buildRequestRaw($personId)
            ->withHeader('x-tbg-auth', Token::create(static::$testPersonUuid, true, 'cus_aaaaaaaaaaaa11'))
            ->withQueryParams([
                'withTipBalances' => $withTipBalance ? 'true' : 'false',
            ]);
    }

    private function buildRequestRaw(string $personId): ServerRequestInterface
    {
        // Accept JSON is the `createRequest()` default.
        return $this->createRequest('GET', '/v1/people/' . $personId);
    }

    /**
     * @param string $mockName          Main Customer mock name
     * @param string|null $piMockName   Payment Intent list ["all"] mock name, if needed.
     */
    private function getStripeClientWithMock(string $mockName, ?string $piMockName = null): Stripe
    {
        $stripeCustomersProphecy = $this->prophesize(CustomerService::class);
        $stripeCustomersProphecy->retrieve(static::$testPersonStripeCustomerId, ['expand' => ['cash_balance']])
            ->shouldBeCalledOnce()
            ->willReturn($this->getStripeObject($mockName));

        $stripeClientProphecy = $this->prophesize(Stripe::class);
        $stripeClientProphecy->customers = $stripeCustomersProphecy->reveal();

        if ($piMockName) {
            $stripePaymentIntentsProphecy = $this->prophesize(PaymentIntentService::class);
            $stripePaymentIntentsProphecy->all(['customer' => self::$testPersonStripeCustomerId])
                ->willReturn(StripeFormatting::buildAutoIterableCollection($this->getMock($piMockName)));
            $stripeClientProphecy->paymentIntents = $stripePaymentIntentsProphecy->reveal();
        }

        return $stripeClientProphecy->reveal();
    }

    private function getStripeObject(string $mockName): StripeObject
    {
        /** @psalm-var array<array-key, mixed> */
        $stripeSingleMockData = json_decode(
            $this->getMock($mockName),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        return StripeObject::constructFrom($stripeSingleMockData);
    }

    private function getMock(string $mockName): string
    {
        return file_get_contents(
            dirname(__DIR__, 3) . '/MockStripeResponses/' . $mockName . '.json'
        );
    }
}
