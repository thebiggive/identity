<?php

namespace BigGive\Identity\Tests\Application\Actions\Person;

use BigGive\Identity\Domain\Person;
use BigGive\Identity\Repository\PersonRepository;
use BigGive\Identity\Tests\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

class ChangePasswordWithTokenTest extends TestCase
{
    use ProphecyTrait;
    public function testCanChangePassword(): void
    {
        $personRepoProphecy = $this->prophesize(PersonRepository::class);
        $personRepoProphecy->persist(Argument::that(function (Person $person) {
            $this->assertSame('n3w-p4ssw0rd', $person->raw_password);
        }))->shouldBeCalledOnce();
    }
}