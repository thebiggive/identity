<?php

namespace BigGive\Identity\IntegrationTests;

use BigGive\Identity\Domain\Person;
use BigGive\Identity\Repository\PersonRepository;
use DI\Container;
use Doctrine\ORM\EntityManagerInterface;
use MatchBot\Application\Messenger\CharityUpdated;
use MatchBot\Application\Messenger\Handler\CharityUpdatedHandler;
use MatchBot\Tests\TestCase;
use Prophecy\Argument;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\RoutableMessageBus;
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
        $sut = clone $this->getService(PersonRepository::class);
        $em = $this->getService(EntityManagerInterface::class);

        $uuid = Uuid::v4();
        $person = new Person();
        $person->setId($uuid);
        // use a unique email address every time to avoid conflict with data already in DB.
        $email = "someemail.$uuid@example.com";
        $person->email_address = $email;
        $person->first_name = "Fred";
        $person->last_name = "Bloggs";
        $person->raw_password = 'password';
        $person->stripe_customer_id = 'cus_1234567890';
        $person->hashPassword();

        $busProphecy = $this->prophesize(RoutableMessageBus::class);
        $busProphecy->dispatch(Argument::type(Envelope::class))
            ->will(
                /**
                 * @param array{0: Envelope} $args
                 */
                function (array $args) use ($person): Envelope {
                    $envelope = $args[0];
                    /** @var \Messages\Person $message */
                    $message = $envelope->getMessage();
                    \PHPUnit\Framework\TestCase::assertInstanceOf(\Messages\Person::class, $message);
                    \PHPUnit\Framework\TestCase::assertSame($person->stripe_customer_id, $message->stripe_customer_id);

                    return $envelope;
                }
            )
            ->shouldBeCalledOnce();
        $sut->setBus($busProphecy->reveal());

        $sut->persist($person, false);
        $em->clear();

        $found = $sut->findPasswordEnabledPersonByEmailAddress($email);

        $this->assertNotSame($person, $found);
        $this->assertNotNull($found);
        $this->assertEquals($email, $found->email_address);
        $this->assertEquals("Fred", $found->first_name);
    }
}
