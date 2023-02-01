<?php

declare(strict_types=1);

namespace BigGive\Identity\Tests\TestPromises;

use BigGive\Identity\Application\Actions\Person\Update;
use BigGive\Identity\Domain\Person;
use BigGive\Identity\Tests\Application\Actions\Person\UpdateTest;
use Prophecy\Promise\PromiseInterface;
use Prophecy\Prophecy\MethodProphecy;
use Prophecy\Prophecy\ObjectProphecy;

class SucceedThenThrowWithDuplicateEmailPromise implements PromiseInterface
{
    private int $callsCount = 0;

    public function __construct(private bool $withPassword)
    {
    }

    public function execute(array $args, ObjectProphecy $object, MethodProphecy $method)
    {
        $person = $args[0];
        assert($person instanceof Person);

        if ($this->callsCount === 0) {
            $this->callsCount++;

            UpdateTest::initialisePerson($person, $this->withPassword);

            return;
        }

        throw new \LogicException(sprintf(
            'Person already exists with password and email address %s',
            $person->email_address ?? '',
        ));
    }
}
