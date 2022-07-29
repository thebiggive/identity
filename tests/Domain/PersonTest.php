<?php

declare(strict_types=1);

namespace BigGive\Identity\Tests\Domain;

use BigGive\Identity\Domain\Person;
use BigGive\Identity\Tests\TestCase;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Doctrine\UuidGenerator;

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
        $em = $this->getAppInstance()->getContainer()->get(EntityManagerInterface::class);

        $person = new Person();
        $person->id = (new UuidGenerator())->generateId($em, $person);
        $person->first_name = 'Loraine';
        $person->last_name = 'James';

        $this->assertEquals(36, strlen($person->getId()->toString()));
        $this->assertEquals('Loraine', $person->getFirstName());
        $this->assertEquals('James', $person->getLastName());
    }

    /**
     * @dataProvider personProvider
     * @param int    $id
     * @param string $username
     * @param string $firstName
     * @param string $lastName
     *
     * @todo test remaining properties.
     */
    public function testJsonSerialize(int $id, string $username, string $firstName, string $lastName): void
    {
        $em = $this->getAppInstance()->getContainer()->get(EntityManagerInterface::class);

        $person = new Person();
        $person->id = (new UuidGenerator())->generateId($em, $person);
        $person->first_name = 'Loraine';
        $person->last_name = 'James';
        $json = $person->jsonSerialize();

        $this->assertEquals('Loraine', $json['first_name']);
        $this->assertEquals('James', $json['last_name']);
    }
}
