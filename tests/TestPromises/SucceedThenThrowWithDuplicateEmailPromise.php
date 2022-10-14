<?php

declare(strict_types=1);

namespace BigGive\Identity\Tests\TestPromises;

use BigGive\Identity\Domain\Person;
use Prophecy\Promise\PromiseInterface;
use Prophecy\Prophecy\MethodProphecy;
use Prophecy\Prophecy\ObjectProphecy;

class SucceedThenThrowWithDuplicateEmailPromise implements PromiseInterface
{
    private int $callsCount = 0;
    private Person $personToReturn;

    public function __construct(Person $personToReturn)
    {
        $this->personToReturn = $personToReturn;
    }

    public function execute(array $args, ObjectProphecy $object, MethodProphecy $method)
    {
        if ($this->callsCount === 0) {
            $this->callsCount++;

            return $this->personToReturn;
        }

        throw new \LogicException(sprintf(
            'Person already exists with password and email address %s',
            $this->personToReturn->email_address,
        ));
    }
}
