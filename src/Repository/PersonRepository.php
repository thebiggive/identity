<?php

declare(strict_types=1);

namespace BigGive\Identity\Repository;

use BigGive\Identity\Domain\DomainException\DomainRecordNotFoundException;
use BigGive\Identity\Domain\Person;

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
     * @param int $id
     * @return Person
     * @throws DomainRecordNotFoundException
     */
    public function findUserOfId(int $id): Person;
}
