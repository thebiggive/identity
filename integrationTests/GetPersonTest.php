<?php

namespace BigGive\Identity\IntegrationTests;

use BigGive\Identity\Application\Auth\Token;
use BigGive\Identity\Domain\Person;
use BigGive\Identity\Repository\PersonRepository;
use GuzzleHttp\Psr7\ServerRequest;
use Symfony\Component\Uid\Uuid;

class GetPersonTest extends IntegrationTest
{
    public function testItReturnsSpecificFieldsFromPersonToHttpGet(): void
    {
        $uuid = $this->addPersonToToDB()->toRfc4122();

        $response = $this->getApp()->handle(new ServerRequest(
            method: 'GET',
            uri: "/v1/people/{$uuid}",
            headers: ['x-tbg-auth' => Token::create($uuid, true, '')],
        ));

        $decodedBody = json_decode($response->getBody()->getContents(), true);
        \assert(is_array($decodedBody));
        $this->assertEqualsCanonicalizing(
            [
                "captcha_code",
                "cash_balance",
                "completion_jwt",
                "created_at",
                "email_address",
                "first_name",
                "has_password",
                "home_address_line_1",
                "home_country_code",
                "home_postcode",
                "id",
                "last_name",
                "pending_tip_balance",
                "skipCaptchaCheck",
                "stripe_customer_id",
                "updated_at",
            ],
            array_keys($decodedBody)
        );
    }

    private function addPersonToToDB(): Uuid
    {
        $person = new Person();
        // use a unique email address every time to avoid conflict with data already in DB.
        $email = "someemail" . Uuid::v4() . "@example.com";
        $person->email_address = $email;
        $person->first_name = "Fred";
        $person->raw_password = 'password';

        $this->getService(PersonRepository::class)->persist($person);

        $uuid = $person->getId();
        \assert($uuid !== null);
        return $uuid;
    }
}
