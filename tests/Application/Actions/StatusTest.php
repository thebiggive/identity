<?php

declare(strict_types=1);

namespace BigGive\Identity\Tests\Application\Actions;

use BigGive\Identity\Application\Actions\ActionPayload;
use BigGive\Identity\Tests\TestCase;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\EventManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySQL80Platform;
use Doctrine\ORM;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Doctrine\ORM\Mapping\Driver\AnnotationDriver;
use Doctrine\ORM\Proxy\ProxyFactory;
use Doctrine\ORM\Tools\Console\Command\GenerateProxiesCommand;
use Doctrine\ORM\Tools\Console\ConsoleRunner;
use Doctrine\ORM\UnitOfWork;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;

class StatusTest extends TestCase
{
    public function setUp(): void
    {
        $this->generateORMProxiesAtRealPath();
    }

    public function testSuccess(): void
    {
        $app = $this->getAppInstance();

        $entityManager = $this->getConnectedMockEntityManager();
        $app->getContainer()->set(EntityManagerInterface::class, $entityManager);

        $request = $this->createRequest('GET', '/ping');
        $response = $app->handle($request);
        $payload = (string) $response->getBody();

        $expectedPayload = new ActionPayload(200, ['status' => 'OK']);
        $expectedSerialised = json_encode($expectedPayload, JSON_PRETTY_PRINT);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($expectedSerialised, $payload);
    }

    public function testDatabaseCannotConnect(): void
    {
        $app = $this->getAppInstance();

        $connectionProphecy = $this->prophesize(Connection::class);
        $connectionProphecy->isConnected()->shouldBeCalledOnce()->willReturn(false);
        $connectionProphecy->connect()->shouldBeCalledOnce()->willReturn(false);

        $entityManagerProphecy = $this->prophesize(EntityManagerInterface::class);
        $entityManagerProphecy->getConnection()->shouldBeCalledTimes(2)->willReturn($connectionProphecy->reveal());

        $app->getContainer()->set(EntityManagerInterface::class, $entityManagerProphecy->reveal());

        $request = $this->createRequest('GET', '/ping');
        $response = $app->handle($request);
        $payload = (string) $response->getBody();

        $expectedPayload = new ActionPayload(500, ['error' => 'Database not connected']);
        $expectedSerialised = json_encode($expectedPayload, JSON_PRETTY_PRINT);

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals($expectedSerialised, $payload);
    }

    public function testMissingDoctrineORMProxy(): void
    {
        $app = $this->getAppInstance();

        // Use a deliberately wrong path so proxies are absent.
        $entityManager = $this->getConnectedMockEntityManager('/tmp/not/this/dir/proxies');
        $app->getContainer()->set(EntityManagerInterface::class, $entityManager);

        $request = $this->createRequest('GET', '/ping');
        $response = $app->handle($request);
        $payload = (string) $response->getBody();

        $expectedPayload = new ActionPayload(500, ['error' => 'Doctrine proxies not built']);
        $expectedSerialised = json_encode($expectedPayload, JSON_PRETTY_PRINT);

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals($expectedSerialised, $payload);
    }

    private function getConnectedMockEntityManager(
        string $proxyPath = '/var/www/html/var/doctrine/proxies',
    ): EntityManagerInterface {
        /** @psalm-suppress DeprecatedMethod **/
        $config = ORM\ORMSetup::createAnnotationMetadataConfiguration(
            ['/var/www/html/src/Domain'],
            false, // Simulate live mode for these tests.
            $proxyPath,
            // For now, we want this class's tests to pass without covering Redis cache simulation
            // » use the Array in-memory cache.
            new ArrayAdapter(),
        );

        // No auto-generation – like live mode – for these tests.
        $config->setAutoGenerateProxyClasses(false);
        $config->setMetadataDriverImpl(
            /** @psalm-suppress DeprecatedClass **/
            new AnnotationDriver(new AnnotationReader(), ['/var/www/html/src/Domain']),
        );

        $connectionProphecy = $this->prophesize(Connection::class);
        $connectionProphecy->isConnected()
            ->willReturn(true);
        // *Can* be called by `GenerateProxiesCommand`.
        $connectionProphecy->getDatabasePlatform()
            ->willReturn(new MySQL80Platform());

        $emProphecy = $this->prophesize(EntityManagerInterface::class);
        $emProphecy->getConfiguration()
            ->willReturn($config);

        $classMetadataFactory = new ClassMetadataFactory();
        // This has to be set on both sides for `ClassMetadataFactory::initialize()` not to crash.
        $classMetadataFactory->setEntityManager($emProphecy->reveal());
        // *Can* be called by `GenerateProxiesCommand`.
        $emProphecy->getMetadataFactory()
            ->willReturn($classMetadataFactory);

        // *Can* be called by `GenerateProxiesCommand`.
        $emProphecy->getEventManager()
            ->willReturn(new EventManager());

        // *Can* be called by `GenerateProxiesCommand`.
        // Mirrors the instantiation in concrete `EntityManager`'s constructor.
        $emProphecy->getUnitOfWork()
            ->shouldBeCalledOnce()
            ->willReturn(new UnitOfWork($emProphecy->reveal()));

        // Mirrors the instantiation in concrete `EntityManager`'s constructor.
        $proxyFactory = new ProxyFactory(
            $emProphecy->reveal(),
            $config->getProxyDir(),
            $config->getProxyNamespace(),
            $config->getAutoGenerateProxyClasses()
        );
        // *Can* be called by `GenerateProxiesCommand`.
        $emProphecy->getProxyFactory()
            ->willReturn($proxyFactory);

        $emProphecy->getConnection()
            ->willReturn($connectionProphecy->reveal());

        return $emProphecy->reveal();
    }

    /**
     * Simulate the real app entrypoint's Doctrine proxy generate command, so that proxies are
     * in-place in the unit test filesystem and we can assume that when realistic paths are provided,
     * the `Status` Action should be able to complete a successful run through.
     */
    private function generateORMProxiesAtRealPath(): void
    {
        $app = $this->getAppInstance();
        $container = $app->getContainer();

        $container->set(EntityManagerInterface::class, $this->getConnectedMockEntityManager());

        $helperSet = ConsoleRunner::createHelperSet($container->get(EntityManagerInterface::class));
        $generateProxiesCommand = new GenerateProxiesCommand();
        $generateProxiesCommand->setHelperSet($helperSet);
        $generateProxiesCommand->run(
            new StringInput(''),
            new NullOutput(),
        );
    }
}
