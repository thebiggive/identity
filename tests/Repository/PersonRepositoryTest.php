<?php

declare(strict_types=1);

namespace BigGive\Identity\Tests\Repository;

use BigGive\Identity\Client\Mailer;
use BigGive\Identity\Domain\Person;
use BigGive\Identity\Repository\PersonRepository;
use BigGive\Identity\Tests\TestCase;
use DI\Container;
use Prophecy\Argument;

class PersonRepositoryTest extends TestCase
{
    public function testRegistrationMailSuccess(): void
    {
        $container = $this->getContainer();

        $mailerProphecy = $this->prophesize(Mailer::class);
        $mailerProphecy
            ->sendEmail(Argument::type('array'))
            ->shouldBeCalledOnce()
            ->willReturn(true);

        $container->set(Mailer::class, $mailerProphecy->reveal());

        $repo = $container->get(PersonRepository::class);
        $repo->setMailerClient($mailerProphecy->reveal());

        $person = new Person();
        $person->email_address = 'foo@example.com';
        $this->assertTrue($repo->sendRegisteredEmail($person));
    }

    public function testRegistrationMailFailure(): void
    {
        $container = $this->getContainer();

        $mailerProphecy = $this->prophesize(Mailer::class);
        $mailerProphecy
            ->sendEmail(Argument::type('array'))
            ->shouldBeCalledOnce()
            ->willReturn(false);

        $container->set(Mailer::class, $mailerProphecy->reveal());

        $repo = $container->get(PersonRepository::class);
        $repo->setMailerClient($mailerProphecy->reveal());

        $person = new Person();
        $person->email_address = 'foo@example.com';
        $this->assertFalse($repo->sendRegisteredEmail($person));
    }
}
