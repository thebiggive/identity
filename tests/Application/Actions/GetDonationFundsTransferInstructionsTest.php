<?php

declare(strict_types=1);

namespace BigGive\Identity\Tests\Application\Actions;

use BigGive\Identity\Application\Auth\TokenService;
use BigGive\Identity\Client\Stripe;
use BigGive\Identity\Repository\PersonRepository;
use BigGive\Identity\Tests\TestCase;
use BigGive\Identity\Tests\TestPeopleTrait;
use Stripe\Service\CustomerService;

class GetDonationFundsTransferInstructionsTest extends TestCase
{
    use TestPeopleTrait;

    public function testSuccess(): void
    {
        $person = $this->getTestPerson(true);

        $app = $this->getAppInstance();

        $secret = getenv('JWT_ID_SECRET');
        \assert(\is_string($secret));
        $tokenService = new TokenService([$secret]);

        $personRepoProphecy = $this->prophesize(PersonRepository::class);
        $personRepoProphecy->find(static::$testPersonUuid)
            ->shouldBeCalledOnce()
            ->willReturn($person);

        $stripeCustomersProphecy = $this->prophesize(CustomerService::class);
        $stripeCustomersProphecy->createFundingInstructions(
            $person->stripe_customer_id,
            [
                'funding_type' => 'bank_transfer',
                'bank_transfer' => [
                    'type' => 'gb_bank_transfer',
                ],
                'currency' => 'gbp',
            ],
        )
            ->shouldBeCalledOnce()
            ->willReturn(json_decode(
                file_get_contents(
                    dirname(__DIR__, 2) . '/MockStripeResponses/funding_instructions.json',
                ),
                false,
                512,
                JSON_THROW_ON_ERROR,
            ));

        $stripeClientProphecy = $this->prophesize(Stripe::class);
        $stripeClientProphecy->customers = $stripeCustomersProphecy->reveal();

        $this->getContainer()->set(PersonRepository::class, $personRepoProphecy->reveal());
        $this->getContainer()->set(Stripe::class, $stripeClientProphecy->reveal());

        $request = $this->createRequest(
            'GET',
            '/v1/people/' . static::$testPersonUuid . '/funding_instructions'
        )
            ->withQueryParams(['currency' => 'gbp'])
            ->withHeader(
                'x-tbg-auth',
                $tokenService->create(new \DateTimeImmutable(), static::$testPersonUuid, true, 'cus_aaaaaaaaaaaa11'),
            );

        $response = $app->handle($request);
        $responseJSON = (string) $response->getBody();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertJson($responseJSON);

        $responseData = json_decode($responseJSON, false, 512, JSON_THROW_ON_ERROR);

        $this->assertEquals('funding_instructions', $responseData->object);
        $this->assertEquals('GB', $responseData->bank_transfer->country);
        $this->assertEquals(
            '04349584',
            $responseData->bank_transfer->financial_addresses[0]->sort_code->account_number,
        );
        $this->assertEquals(
            'The Big Give',
            $responseData->bank_transfer->financial_addresses[0]->sort_code->account_holder_name,
        );
    }
}
