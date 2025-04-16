<?php

namespace BigGive\Identity\IntegrationTests;

use BigGive\Identity\Client\Stripe;
use BigGive\Identity\Domain\Person;
use BigGive\Identity\Repository\PersonRepository;
use DI\Container;
use LosMiddleware\RateLimit\RateLimitMiddleware;
use LosMiddleware\RateLimit\RateLimitOptions;
use Mezzio\ProblemDetails\ProblemDetailsResponseFactory;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Slim\App;
use Slim\Factory\AppFactory;
use Stripe\Service\CustomerService;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\RoutableMessageBus;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints\NotCompromisedPasswordValidator;

abstract class IntegrationTest extends TestCase
{
    use ProphecyTrait;

    private ?ContainerInterface $container = null;
    private ?App $app = null;

    public function setUp(): void
    {
        $this->rebuildApp();
    }

    /**
     * Stub Stripe `customers` service calls (for now) and set logger to NullLogger.
     */
    private function stubStripeAndLogger(Container $container): void
    {
        $this->stubOutStripeCustomers($container);
        $container->set(LoggerInterface::class, new NullLogger());
    }

    private function getContainer(): ContainerInterface
    {
        if ($this->container === null) {
            throw new \Exception("Test container not set");
        }

        $container = $this->container;
        $this->assertInstanceOf(Container::class, $container);
        $this->stubStripeAndLogger($container);

        return $this->container;
    }

    protected function getWriteableContainer(): Container
    {
        $container = $this->getContainer();
        \assert($container instanceof Container);
        return $container;
    }

    protected function getApp(): App
    {
        if ($this->app === null) {
            throw new \Exception("Test app not set");
        }
        return $this->app;
    }

    /**
     * @template T
     * @param class-string<T> $name
     * @return T
     */
    protected function getService(string $name): mixed
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

        $this->getService(PersonRepository::class)->persist($person, false);

        $uuid = $person->getId();
        \assert($uuid !== null);
        return $uuid;
    }

    /**
     * We rebuild the app before each test case to keep tests independent - each test has its own test double config.
     */
    private function rebuildApp(): void
    {
        $container = require __DIR__ . '/../bootstrap.php';
        $this->container = $container;

        AppFactory::setContainer($container);
        $this->app = AppFactory::create();

        /** @psalm-suppress MixedArgument */
        $container->set(RateLimitMiddleware::class, new class (
            $container->get(Psr16Cache::class),
            $container->get(ProblemDetailsResponseFactory::class),
            new RateLimitOptions(),
        ) extends RateLimitMiddleware {
            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler
            ): ResponseInterface {
                // skip rate limiting for tests.
                return $handler->handle($request);
            }
        });

        $container->set(TransportInterface::class, new InMemoryTransport());

        $routes = require __DIR__ . '/../app/routes.php';
        $routes($this->app);
    }
}
