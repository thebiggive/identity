<?php

declare(strict_types=1);

use BigGive\Identity\Application\Settings\SettingsInterface;
use DI\ContainerBuilder;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\Driver\AnnotationDriver;
use LosMiddleware\RateLimit\RateLimitMiddleware;
use LosMiddleware\RateLimit\RateLimitOptions;
use Mezzio\ProblemDetails\ProblemDetailsResponseFactory;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\UidProcessor;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use ReCaptcha\ReCaptcha;
use ReCaptcha\RequestMethod\CurlPost;
use Slim\Psr7\Factory\ResponseFactory;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;

return function (ContainerBuilder $containerBuilder) {
    $containerBuilder->addDefinitions([
        CacheInterface::class => function (ContainerInterface $c): CacheInterface {
            return new Psr16Cache(
                new Symfony\Component\Cache\Adapter\RedisAdapter(
                    $c->get(Redis::class),
                    // Distinguish rate limit data etc. from other apps + use cases.
                    'identity-cache',
                    3600, // Allow Auto-clearing cache/rate limit data after an hour.
                ),
            );
        },

        EntityManagerInterface::class => static function (ContainerInterface $c): EntityManagerInterface {
            // https://github.com/ramsey/uuid-doctrine#innodb-optimised-binary-uuids
            // Tests seem to hit this multiple times and get unhappy, so we must check
            // for a previous invocation with `hasType()`.
            if (!Type::hasType('uuid_binary_ordered_time')) {
                Type::addType('uuid_binary_ordered_time', UuidBinaryOrderedTimeType::class);
            }

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

            // TODO Pass $cache as 4th arg once it's ready.
            $config = ORM\ORMSetup::createAnnotationMetadataConfiguration(
                $doctrineSettings['metadata_dirs'],
                $doctrineSettings['dev_mode'],
                $doctrineSettings['cache_dir'] . '/proxies',
                new ArrayAdapter(),
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

        ProblemDetailsResponseFactory::class => static function (ContainerInterface $c): ProblemDetailsResponseFactory {
            return new ProblemDetailsResponseFactory(new ResponseFactory());
        },

        RateLimitMiddleware::class => static function (ContainerInterface $c): RateLimitMiddleware {
            return new RateLimitMiddleware(
                $c->get(CacheInterface::class),
                $c->get(ProblemDetailsResponseFactory::class),
                new RateLimitOptions($c->get(SettingsInterface::class)->get('los_rate_limit')),
            );
        },

        ReCaptcha::class => static function (ContainerInterface $c): ReCaptcha {
            return new ReCaptcha($c->get(SettingsInterface::class)->get('recaptcha')['secret_key'], new CurlPost());
        },

        SerializerInterface::class => static function (ContainerInterface $c): SerializerInterface {
            $encoders = [new JsonEncoder()];
            $normalizers = [new ObjectNormalizer()];

            return new Serializer($normalizers, $encoders);
        },
    ]);
};
