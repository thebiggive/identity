<?php

namespace BigGive\Identity\IntegrationTests;

use BigGive\Identity\Client\Stripe;
use BigGive\Identity\Domain\Person;
use BigGive\Identity\Repository\PersonRepository;
use DI\Container;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Slim\App;
use Stripe\Service\CustomerService;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\RoutableMessageBus;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints\NotCompromisedPasswordValidator;

abstract class IntegrationTest extends TestCase
{
    use ProphecyTrait;

    public static ?ContainerInterface $integrationTestContainer = null;
    public static ?App $app = null;

    /**
     * Keeping a copy of the original state of the person repo in memory to allow restoring after test is finished
     * to avoid interference with later tests
     */
    private PersonRepository $originalPersonRepository;

    public function setUp(): void
    {
        $this->originalPersonRepository = $this->getService(PersonRepository::class);
    }

    public function tearDown(): void
    {
        $this->getWriteableContainer()->set(PersonRepository::class, $this->originalPersonRepository);
    }

    public static function setContainer(ContainerInterface $container): void
    {
        self::$integrationTestContainer = $container;
    }

    public static function setApp(App $app): void
    {
        self::$app = $app;
    }

    /**
     * Stub Stripe `customers` service calls (for now) and set logger to NullLogger.
     */
    public function stubStripeAndLogger(Container $container): void
    {
        $this->stubOutStripeCustomers($container);
        $container->set(LoggerInterface::class, new NullLogger());
    }

    protected function getContainer(): ContainerInterface
    {
        if (self::$integrationTestContainer === null) {
            throw new \Exception("Test container not set");
        }

        $container = self::$integrationTestContainer;
        $this->assertInstanceOf(Container::class, $container);
        $this->stubStripeAndLogger($container);

        return self::$integrationTestContainer;
    }

    public function getWriteableContainer(): Container
    {
        $container = $this->getContainer();
        \assert($container instanceof Container);
        return $container;
    }

    protected function getApp(): App
    {
        if (self::$app === null) {
            throw new \Exception("Test app not set");
        }
        return self::$app;
    }

    /**
     * @template T
     * @param class-string<T> $name
     * @return T
     */
    public function getService(string $name): mixed
    {
        $service = $this->getContainer()->get($name);
        $this->assertInstanceOf($name, $service);

        return $service;
    }

    private function stubOutStripeCustomers(Container $container): void
    {
        $stripeCustomers = $this->createStub(CustomerService::class);
        $stripeProphecy = $this->prophesize(Stripe::class);
        $stripeProphecy->customers = $stripeCustomers;
        $container->set(Stripe::class, $stripeProphecy->reveal());
    }

    /**
     * @param string $emailAddress - use a unique email address every time to avoid conflict with data already in DB.
     */
    protected function addPersonToToDB(string $emailAddress): Uuid
    {
        $person = new Person(
            notCompromisedPasswordValidator: $this->createStub(NotCompromisedPasswordValidator::class)
        );
        $person->email_address = $emailAddress;
        $person->first_name = "Fred";
        $person->last_name = "Bloggs";
        $person->stripe_customer_id = 'cus_1234567890';

        $this->getService(PersonRepository::class)->persist($person);

        $uuid = $person->getId();
        \assert($uuid !== null);
        return $uuid;
    }
}
