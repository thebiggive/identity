<?php

declare(strict_types=1);

namespace BigGive\Identity\Tests\Repository;

use BigGive\Identity\Client\Mailer;
use BigGive\Identity\Domain\Person;
use BigGive\Identity\Repository\PersonRepository;
use BigGive\Identity\Tests\TestCase;
use Prophecy\Argument;

class PersonRepositoryTest extends TestCase
{
    public function testRegistrationMailSuccess(): void
    {
        $app = $this->getAppInstance();
        $container = $app->getContainer();

        $mailerProphecy = $this->prophesize(Mailer::class);
        $mailerProphecy
            ->sendEmail(Argument::type('array'))
            ->shouldBeCalledOnce()
            ->willReturn(true);

        $container->set(Mailer::class, $mailerProphecy->reveal());

        $repo = $container->get(PersonRepository::class);
        $repo->setMailerClient($mailerProphecy->reveal());

        $this->assertTrue($repo->sendRegisteredEmail(new Person()));
    }

    public function testRegistrationMailFailure(): void
    {
        $app = $this->getAppInstance();
        $container = $app->getContainer();

        $mailerProphecy = $this->prophesize(Mailer::class);
        $mailerProphecy
            ->sendEmail(Argument::type('array'))
            ->shouldBeCalledOnce()
            ->willReturn(false);

        $container->set(Mailer::class, $mailerProphecy->reveal());

        $repo = $container->get(PersonRepository::class);
        $repo->setMailerClient($mailerProphecy->reveal());

        $this->assertFalse($repo->sendRegisteredEmail(new Person()));
    }
}
