<?php

namespace BigGive\Identity\IntegrationTests;

use BigGive\Identity\Application\Auth\Token;
use BigGive\Identity\Domain\Person;
use BigGive\Identity\Repository\PersonRepository;
use GuzzleHttp\Psr7\ServerRequest;
use Prophecy\Argument;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\RoutableMessageBus;
use Symfony\Component\Uid\Uuid;

class GetPersonTest extends IntegrationTest
{
    public function testItReturnsSpecificFieldsFromPersonToHttpGet(): void
    {
        $uuid = $this->addPersonToToDB("someemail" . Uuid::v4() . "@example.com")->toRfc4122();

        $response = $this->getApp()->handle(new ServerRequest(
            method: 'GET',
            uri: "/v1/people/{$uuid}",
            headers: ['x-tbg-auth' => Token::create($uuid, true, '')],
        ));

        $decodedBody = json_decode($response->getBody()->getContents(), true);
        \assert(is_array($decodedBody));
        $this->assertEqualsCanonicalizing(
            [
                "cash_balance",
                "completion_jwt",
                "email_address",
                "first_name",
                "has_password",
                "home_address_line_1",
                "home_country_code",
                "home_postcode",
                "id",
                "last_name",
                "pending_tip_balance",
                "recently_confirmed_tips_total",
                "stripe_customer_id",
            ],
            array_keys($decodedBody)
        );
    }
}
