<?php

namespace BigGive\Identity\IntegrationTests;

use BigGive\Identity\Client\Stripe;
use DI\Container;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Slim\App;
use Stripe\Service\CustomerService;

abstract class IntegrationTest extends TestCase
{
    use ProphecyTrait;

    public static ?ContainerInterface $integrationTestContainer = null;
    public static ?App $app = null;

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
}
