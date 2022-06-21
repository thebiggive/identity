<?php

declare(strict_types=1);

use BigGive\Identity\Application\Settings\SettingsInterface;
use DI\ContainerBuilder;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\ORM;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\Driver\AnnotationDriver;
use Doctrine\ORM\Tools\Setup;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\UidProcessor;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

return function (ContainerBuilder $containerBuilder) {
    $containerBuilder->addDefinitions([
        EntityManagerInterface::class => static function (ContainerInterface $c): EntityManagerInterface {
            return EntityManager::create(
                $c->get(SettingsInterface::class)->get('doctrine')['connection'],
                $c->get(ORM\Configuration::class),
            );
        },

        LoggerInterface::class => function (ContainerInterface $c) {
            $settings = $c->get(SettingsInterface::class);

            $loggerSettings = $settings->get('logger');
            $logger = new Logger($loggerSettings['name']);

            $processor = new UidProcessor();
            $logger->pushProcessor($processor);

            $handler = new StreamHandler($loggerSettings['path'], $loggerSettings['level']);
            $logger->pushHandler($handler);

            return $logger;
        },

        ORM\Configuration::class => static function (ContainerInterface $c): ORM\Configuration {
            $doctrineSettings = $c->get(SettingsInterface::class)->get('doctrine');

            // TODO use Redis.
//            $redis = new Redis();
//            try {
//                $redis->connect($c->get('settings')['redis']['host']);
//                $cache = new RedisCache();
//                $cache->setRedis($redis);
//                $cache->setNamespace("matchbot-{$settings['appEnv']}");
//            } catch (RedisException $exception) {
//                $cache = new ArrayCache();
//            }

            $config = Setup::createAnnotationMetadataConfiguration(
                $doctrineSettings['metadata_dirs'],
                $doctrineSettings['dev_mode'],
                $doctrineSettings['cache_dir'] . '/proxies',
//                $cache
            );

            // Turn off auto-proxies in ECS envs, where we explicitly generate them on startup entrypoint and cache all
            // files indefinitely.
            $config->setAutoGenerateProxyClasses($doctrineSettings['dev_mode']);

            $config->setMetadataDriverImpl(
                new AnnotationDriver(new AnnotationReader(), $doctrineSettings['metadata_dirs'])
            );

//            $config->setMetadataCacheImpl($cache);

            return $config;
        },
    ]);
};
