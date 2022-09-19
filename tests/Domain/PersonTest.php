<?php

declare(strict_types=1);

namespace BigGive\Identity\Tests\Domain;

use BigGive\Identity\Domain\Person;
use BigGive\Identity\Tests\TestCase;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Uid\Uuid;

class PersonTest extends TestCase
{
    public function personProvider(): array
    {
        return [
            [1, 'bill.gates', 'Bill', 'Gates'],
            [2, 'steve.jobs', 'Steve', 'Jobs'],
            [3, 'mark.zuckerberg', 'Mark', 'Zuckerberg'],
            [4, 'evan.spiegel', 'Evan', 'Spiegel'],
            [5, 'jack.dorsey', 'Jack', 'Dorsey'],
        ];
    }

    /**
     * @dataProvider personProvider
     * @param int    $id
     * @param string $username
     * @param string $firstName
     * @param string $lastName
     */
    public function testGetters(int $id, string $username, string $firstName, string $lastName): void
    {
        $person = new Person();
        $person->id = Uuid::v4();
        $person->first_name = 'Loraine';
        $person->last_name = 'James';

        // Cast to string to use symfony/uid's default normalisation.
        $this->assertEquals(36, strlen((string) $person->getId()));
        $this->assertEquals('Loraine', $person->getFirstName());
        $this->assertEquals('James', $person->getLastName());
    }

    /**
     * @dataProvider personProvider
     * @param int    $id
     * @param string $username
     * @param string $firstName
     * @param string $lastName
     */
    public function testJsonSerialize(int $id, string $username, string $firstName, string $lastName): void
    {
        $person = new Person();
        $person->id = Uuid::v4();
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
}
