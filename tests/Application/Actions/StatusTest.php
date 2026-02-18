<?php

declare(strict_types=1);

namespace BigGive\Identity\Tests\Application\Actions;

use BigGive\Identity\Application\Actions\ActionPayload;
use BigGive\Identity\Tests\TestCase;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\ORM;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class StatusTest extends TestCase
{
    public function testSuccess(): void
    {
        $app = $this->getAppInstance();

        $entityManager = $this->getConnectedMockEntityManager();
        $this->getContainer()->set(EntityManagerInterface::class, $entityManager);

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
        $connectionProphecy->getNativeConnection()
            ->shouldBeCalledOnce()
            ->willThrow(new \Doctrine\DBAL\Exception\ConnectionException());

        $entityManagerProphecy = $this->prophesize(EntityManagerInterface::class);
        $entityManagerProphecy->getConnection()->shouldBeCalledOnce()->willReturn($connectionProphecy->reveal());

        $this->getContainer()->set(EntityManagerInterface::class, $entityManagerProphecy->reveal());

        $request = $this->createRequest('GET', '/ping');
        $response = $app->handle($request);
        $payload = (string) $response->getBody();

        $expectedPayload = new ActionPayload(500, ['error' => 'Database connection failed']);
        $expectedSerialised = json_encode($expectedPayload, JSON_PRETTY_PRINT);

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals($expectedSerialised, $payload);
    }

    private function getConnectedMockEntityManager(): EntityManagerInterface
    {
        $config = ORM\ORMSetup::createAttributeMetadataConfiguration(
            ['/var/www/html/src/Domain'],
            false, // Simulate live mode for these tests.
            '/var/www/html/var/doctrine/proxies',
            // For now, we want this class's tests to pass without covering Redis cache simulation
            // » use the Array in-memory cache.
            new ArrayAdapter(),
        );

        // Enable native lazy objects - no proxy generation needed with PHP 8.4+.
        $config->enableNativeLazyObjects(true);

        $config->setMetadataDriverImpl(
            new AttributeDriver(['/var/www/html/src/Domain']),
        );

        $connectionProphecy = $this->prophesize(Connection::class);
        $connectionProphecy->getNativeConnection()
            ->willReturn(new \PDO('sqlite::memory:'));
        $connectionProphecy->getDatabasePlatform()
            ->willReturn(new MySQLPlatform());

        $emProphecy = $this->prophesize(EntityManagerInterface::class);
        $emProphecy->getConfiguration()
            ->willReturn($config);

        $emProphecy->getConnection()
            ->willReturn($connectionProphecy->reveal());

        return $emProphecy->reveal();
    }
}
