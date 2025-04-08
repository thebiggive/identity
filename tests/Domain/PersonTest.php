<?php

declare(strict_types=1);

namespace BigGive\Identity\Tests\Domain;

use BigGive\Identity\Domain\Person;
use BigGive\Identity\Tests\TestCase;
use Monolog\DateTimeImmutable;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Uid\Uuid;

class PersonTest extends TestCase
{
    public function testGetters(): void
    {
        $person = $this->getPersonWithKeyFieldsSet();

        // Cast to string to use symfony/uid's default normalisation.
        $this->assertEquals(36, strlen((string) $person->getId()));
        $this->assertEquals('Loraine', $person->getFirstName());
        $this->assertEquals('James', $person->getLastName());
    }

    public function testJsonSerialize(): void
    {
        $person = $this->getPersonWithKeyFieldsSet();
        $json = $this->getAppInstance()->getContainer()->get(SerializerInterface::class)
            ->serialize($person, 'json');
        $jsonData = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $this->assertEquals('Loraine', $jsonData['first_name']);
        $this->assertEquals('James', $jsonData['last_name']);
        $this->assertEquals('loraine@hyperdub.example.net', $jsonData['email_address']);
    }

    public function testToMailerPayload(): void
    {
        $person = $this->getPersonWithKeyFieldsSet();
        $payload = $person->toMailerPayload();

        $expectedPayload = [
            'templateKey' => 'donor-registered',
            'recipientEmailAddress' => 'loraine@hyperdub.example.net',
            'forGlobalCampaign' => false,
            'params' => [
                'donorFirstName' => 'Loraine',
                'donorEmail' => 'loraine@hyperdub.example.net',
            ],
        ];

        $this->assertEquals($expectedPayload, $payload);
    }

    public function testToMatchBotSummaryMessage(): void
    {
        $person = $this->getPersonWithKeyFieldsSet();
        $message = $person->toMatchBotSummaryMessage();

        $this->assertInstanceOf(\Messages\Person::class, $message);
        $this->assertEquals('Loraine', $message->first_name);
        $this->assertEquals('James', $message->last_name);
        $this->assertEquals('loraine@hyperdub.example.net', $message->email_address);
        $this->assertEquals('cus_1234567890', $message->stripe_customer_id);
    }

    private function getPersonWithKeyFieldsSet(): Person
    {
        $person = new Person();
        $person->setId(Uuid::v4());
        $person->first_name = 'Loraine';
        $person->last_name = 'James';
        $person->email_address = 'loraine@hyperdub.example.net';
        $person->stripe_customer_id = 'cus_1234567890';

        return $person;
    }

    public function testItGeneratesEmailVerificationCode(): void
    {
        $person = new Person();
        $time = new \DateTimeImmutable('2025-01-01 00:00:00');

        $randomizer = new Randomizer(new Mt19937(1));
        $person->setRandomEmailVerificationCode($time, $randomizer);

        $this->assertFalse($person->emailAddressVerified);
        $this->assertSame($time, $person->emailAddressVerificationCodeGeneratedAt);
        $this->assertEquals('541077', $person->emailAddressVerificationCode);
    }
}
