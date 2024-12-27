<?php

namespace BigGive\Identity\IntegrationTests;

use BigGive\Identity\Domain\Person;
use BigGive\Identity\Repository\PersonRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

class PersonRepositoryTest extends IntegrationTest
{
    public function testItDoesNotFindPersonWhoDoesNotExist(): void
    {
        $sut = $this->getService(PersonRepository::class);

        $found = $sut->find(Uuid::v4());

        $this->assertNull($found);
    }

    public function testItPersistsAndFindsPerson(): void
    {
        $sut = $this->getService(PersonRepository::class);
        $em = $this->getService(EntityManagerInterface::class);

        $person = new Person();
        // use a unique email address every time to avoid conflict with data already in DB.
        $email = "someemail" . Uuid::v4() . "@example.com";
        $person->email_address = $email;
        $person->first_name = "Fred";
        $person->last_name = "Bloggs";
        $person->raw_password = 'password';
        $person->stripe_customer_id = 'cus_1234567890';

        $sut->persist($person);
        $em->clear();

        $found = $sut->findPasswordEnabledPersonByEmailAddress($email);

        $this->assertNotSame($person, $found);
        $this->assertNotNull($found);
        $this->assertEquals($email, $found->email_address);
        $this->assertEquals("Fred", $found->first_name);
    }
}
