<?php

declare(strict_types=1);

namespace BigGive\Identity\Tests\Domain;

use BigGive\Identity\Domain\Person;
use BigGive\Identity\Tests\TestCase;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Uid\Uuid;

class PersonTest extends TestCase
{
    public function testGetters(): void
    {
        $person = new Person();
        $person->setId(Uuid::v4());
        $person->first_name = 'Loraine';
        $person->last_name = 'James';

        // Cast to string to use symfony/uid's default normalisation.
        $this->assertEquals(36, strlen((string) $person->getId()));
        $this->assertEquals('Loraine', $person->getFirstName());
        $this->assertEquals('James', $person->getLastName());
    }

    public function testJsonSerialize(): void
    {
        $person = new Person();
        $person->setId(Uuid::v4());
        $person->first_name = 'Loraine';
        $person->last_name = 'James';
        $person->email_address = 'loraine@hyperdub.net';
        $json = $this->getAppInstance()->getContainer()->get(SerializerInterface::class)
            ->serialize($person, 'json');
        $jsonData = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $this->assertEquals('Loraine', $jsonData['first_name']);
        $this->assertEquals('James', $jsonData['last_name']);
        $this->assertEquals('loraine@hyperdub.net', $jsonData['email_address']);
    }

    public function testToMailerPayload(): void
    {
        $person = new Person();
        $person->first_name = 'Loraine';
        $person->email_address = 'loraine@hyperdub.net';
        $payload = $person->toMailerPayload();

        $expectedPayload = [
            'templateKey' => 'donor-registered',
            'recipientEmailAddress' => 'loraine@hyperdub.net',
            'forGlobalCampaign' => false,
            'params' => [
                'donorFirstName' => 'Loraine',
                'donorEmail' => 'loraine@hyperdub.net',
            ],
        ];

        $this->assertEquals($expectedPayload, $payload);
    }
}
