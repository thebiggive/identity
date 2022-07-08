<?php

declare(strict_types=1);

namespace BigGive\Identity\Repository;

use BigGive\Identity\Domain\DomainException\DomainRecordNotFoundException;
use BigGive\Identity\Domain\Person;
use Ramsey\Uuid\UuidInterface;

/**
 * @todo implement or drop.
 */
interface PersonRepository
{
    /**
     * @return Person[]
     */
    public function findAll(): array;

    /**
     * @param UuidInterface $id
     * @return Person
     * @throws DomainRecordNotFoundException
     */
    public function findPersonOfId(UuidInterface $id): Person;
}
