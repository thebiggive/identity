<?php

namespace BigGive\Identity\IntegrationTests;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Slim\App;

abstract class IntegrationTest extends TestCase
{
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

    protected function getContainer(): ContainerInterface
    {
        if (self::$integrationTestContainer === null) {
            throw new \Exception("Test container not set");
        }
        return self::$integrationTestContainer;
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
     */ public function getService(string $name): mixed
    {
        $service = $this->getContainer()->get($name);
        $this->assertInstanceOf($name, $service);

        return $service;
    }
}
