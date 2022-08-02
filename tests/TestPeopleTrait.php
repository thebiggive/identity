<?php

namespace BigGive\Identity\Tests;

use BigGive\Identity\Domain\Person;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Doctrine\UuidGenerator;

trait TestPeopleTrait
{
    private EntityManagerInterface $em;

    public function setUp(): void
    {
        $this->em = $this->getAppInstance()->getContainer()->get(EntityManagerInterface::class);
    }

    /**
     * @param bool $withId  Sets a string UUID; must be false if passing to middleware that assumes
     *                      a real UUID object.
     * @return Person
     */
    private function getTestPerson(bool $withId = false): Person
    {
        $person = new Person();
        $person->first_name = 'Loraine';
        $person->last_name = 'James';
        $person->raw_password = 'superSecure123';
        $person->email_address = 'loraine@hyperdub.net';

        if ($withId) {
            $person->id = (new UuidGenerator())->generateId($this->em, $person);
        }

        return $person;
    }
}
