<?php

declare(strict_types=1);

namespace BigGive\Identity\Tests\Application\Actions\Person;

use BigGive\Identity\Application\Middleware\FriendlyCaptchaVerifier;
use BigGive\Identity\Client;
use BigGive\Identity\Domain\Person;
use BigGive\Identity\Repository\PersonRepository;
use BigGive\Identity\Tests\TestCase;
use BigGive\Identity\Tests\TestPeopleTrait;
use Doctrine\ORM\EntityManagerInterface;
use Prophecy\Argument;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpUnauthorizedException;
use Stripe\Service\CustomerService;

class CreateTest extends TestCase
{
    use TestPeopleTrait;

    public function testSuccess(): void
    {
        $app = $this->getAppInstance();

        $personRepoProphecy = $this->prophesize(PersonRepository::class);
        $personRepoProphecy->persist(Argument::type(Person::class), Argument::type('bool'))
            ->shouldBeCalledTimes(2) // Currently once for stable UUID, once w/ Stripe Customer ID.
            ->will(/**
             * @param array<Person> $args
             */                fn (array $args) => CreateTest::initialisePerson($args[0], false)
            );

        $customerMockResult = (object) [
            'id' => static::$testPersonStripeCustomerId,
            'object' => 'customer',
        ];

        $stripeCustomersProphecy = $this->prophesize(CustomerService::class);
        $stripeCustomersProphecy->create($this->getStripeCustomerCommonArgs())
            ->willReturn($customerMockResult)
            ->shouldBeCalledOnce();
        $stripeClientProphecy = $this->prophesize(Client\Stripe::class);
        $stripeClientProphecy->customers = $stripeCustomersProphecy->reveal();

        $this->getContainer()->set(PersonRepository::class, $personRepoProphecy->reveal());
        $this->getContainer()->set(Client\Stripe::class, $stripeClientProphecy->reveal());

        $request = $this->buildRequest([
            'captcha_code' => 'good response',
            'first_name' => 'Loraine',
            'last_name' => 'James',
        ]);

        $response = $app->handle($request);
        $payloadJSON = (string) $response->getBody();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertJson($payloadJSON);

        /** @var object $payload */
        $payload = json_decode($payloadJSON, false, 512, JSON_THROW_ON_ERROR);

        // Mocked PersonRepository sets a UUID in code.
        $this->assertSame(36, strlen((string) $payload->id));

        $this->assertEquals('Loraine', $payload->first_name);

        $this->assertFalse($payload->has_password);
        $this->assertObjectNotHasProperty('raw_password', $payload);
        $this->assertObjectNotHasProperty('password', $payload);
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
        ], JSON_THROW_ON_ERROR);
        $this->assertJsonStringEqualsJsonString($expectedJSON, $payloadJSON);
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
