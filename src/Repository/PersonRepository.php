<?php

declare(strict_types=1);

namespace BigGive\Identity\Repository;

use BigGive\Identity\Domain\Person;
use Doctrine\ORM\EntityRepository;

class PersonRepository extends EntityRepository
{
    public function findPersonByEmailAddress(string $emailAddress): ?Person
    {
        return $this->findOneBy(['email_address' => $emailAddress]);
    }

    /**
     * This has its own thin repo method largely so we can mock it in tests and simulate
     * side effects like setting a binary UUID, without having tests depend upon a real
     * database.
     *
     * It also sets the password hash and EM-flushes the entity as side effects.
     *
     * @return Person   The Person, with any persist side effect properties set (or simulated
     *                  set when testing).
     */
    public function persist(Person $person): Person
    {
        $person->hashPassword();

        $this->getEntityManager()->persist($person);
        $this->getEntityManager()->flush();

        return $person;
    }
}
