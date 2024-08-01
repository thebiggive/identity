<?php

declare(strict_types=1);

use BigGive\Identity\Application\Middleware\FriendlyCaptchaVerifier;
use BigGive\Identity\Application\Settings\SettingsInterface;
use BigGive\Identity\Client;
use BigGive\Identity\Client\Mailer;
use BigGive\Identity\Domain\Normalizers\HasPasswordNormalizer;
use DI\ContainerBuilder;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use GuzzleHttp\Client as GuzzleClient;
use LosMiddleware\RateLimit\RateLimitMiddleware;
use LosMiddleware\RateLimit\RateLimitOptions;
use Mezzio\ProblemDetails\ProblemDetailsResponseFactory;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\UidProcessor;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use ReCaptcha\ReCaptcha;
use ReCaptcha\RequestMethod\CurlPost;
use Slim\Psr7\Factory\ResponseFactory;
use Stripe\Util\ApiVersion;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\PropertyNormalizer;
use Symfony\Component\Serializer\Normalizer\UidNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

return function (ContainerBuilder $containerBuilder) {
    $containerBuilder->addDefinitions([
        CacheItemPoolInterface::class => function (ContainerInterface $c): CacheItemPoolInterface {
            $redis = $c->get(Redis::class);
            if ($redis === null) {
                // Should never happen live except during major infrastructure issues.
                // This is presumed to be one better than `NullAdapter` in that case since
                // a single ECS task/request can hopefully do some short term caching.
                // We should also have already logged a warning during Redis::class resolution.
                return new ArrayAdapter();
            }

            return new RedisAdapter(
                $c->get(Redis::class),
                // Distinguish rate limit data etc. from other apps + use cases.
                "identity-{$c->get(SettingsInterface::class)->get('appEnv')}",
                3600, // Allow Auto-clearing cache/rate limit data after an hour.
            );
        },
        Connection::class => static function (ContainerInterface $c): Connection {
            $em = $c->get(EntityManagerInterface::class);
            \assert($em instanceof EntityManagerInterface);

            return $em->getConnection();
        },

        EntityManagerInterface::class => static function (ContainerInterface $c): EntityManagerInterface {
            if (!Type::hasType('uuid')) {
                Type::addType('uuid', UuidType::class);
            }

            /** @psalm-suppress DeprecatedMethod */
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

        Mailer::class => static function (ContainerInterface $c): Mailer {
            /** @var SettingsInterface $settings */
            $settings = $c->get(SettingsInterface::class);

            /** @var LoggerInterface $logger */
            $logger = $c->get(LoggerInterface::class);

            return new Mailer(
                new GuzzleClient([
                    'timeout' => $settings->get('apiClient')['global']['timeout'],
                ]),
                $settings,
                $logger,
            );
        },

        ORM\Configuration::class => static function (ContainerInterface $c): ORM\Configuration {
            $cache = $c->get(CacheItemPoolInterface::class);
            $settings = $c->get(SettingsInterface::class);
            $doctrineSettings = $settings->get('doctrine');

            $config = ORM\ORMSetup::createAttributeMetadataConfiguration(
                $doctrineSettings['metadata_dirs'],
                $doctrineSettings['dev_mode'],
                $doctrineSettings['cache_dir'] . '/proxies',
                $cache,
            );

            // Turn off auto-proxies in ECS envs, where we explicitly generate them on startup entrypoint and cache all
            // files indefinitely.
            $config->setAutoGenerateProxyClasses($doctrineSettings['dev_mode']);

            $config->setMetadataDriverImpl(
                new AttributeDriver($doctrineSettings['metadata_dirs']),
            );

            // Note that we *don't* use a result cache for this app, for both functional and security
            // reasons:
            // * We don't want old copies of data for critical donor functions.
            // * We don't want PII in non-encrypted Redis, for which speed is the priority.
            $config->setHydrationCache($cache);
            $config->setMetadataCache($cache);
            $config->setQueryCache($cache);

            return $config;
        },

        ProblemDetailsResponseFactory::class => static function (): ProblemDetailsResponseFactory {
            return new ProblemDetailsResponseFactory(new ResponseFactory());
        },

        Psr16Cache::class => function (ContainerInterface $c): Psr16Cache {
            return new Psr16Cache($c->get(CacheItemPoolInterface::class));
        },

        RateLimitMiddleware::class => static function (ContainerInterface $c): RateLimitMiddleware {
            return new RateLimitMiddleware(
                $c->get(Psr16Cache::class),
                $c->get(ProblemDetailsResponseFactory::class),
                new RateLimitOptions($c->get(SettingsInterface::class)->get('los_rate_limit')),
            );
        },

        ReCaptcha::class => static function (ContainerInterface $c): ReCaptcha {
            return new ReCaptcha($c->get(SettingsInterface::class)->get('recaptcha')['secret_key'], new CurlPost());
        },

        FriendlyCaptchaVerifier::class => static function (ContainerInterface $c): FriendlyCaptchaVerifier {
        /** @var array{api_key: string, site_key: string} $settings */
            $settings = $c->get(SettingsInterface::class)->get('friendly_captcha');

            $client = $c->get(GuzzleClient::class);
            \assert($client instanceof GuzzleClient);

            return new FriendlyCaptchaVerifier(
                client: $client,
                secret: $settings['api_key'],
                siteKey: $settings['site_key'],
            );
        },

        // Note that *unlike MatchBot* we share the same instance with Doctrine + other stuff,
        // as we do not require the serializer option to be set off for anything in this app.
        Redis::class => static function (ContainerInterface $c): ?Redis {
            $redis = new Redis();
            try {
                $redis->connect($c->get(SettingsInterface::class)->get('redis')['host']);
            } catch (RedisException $exception) {
                $c->get(LoggerInterface::class)->warning(sprintf(
                    'Redis connect() got RedisException: "%s". Host %s',
                    $exception->getMessage(),
                    $c->get(SettingsInterface::class)->get('redis')['host'],
                ));

                return null;
            }

            return $redis;
        },

        SerializerInterface::class => static function (ContainerInterface $c): SerializerInterface {
            $encoders = [new JsonEncoder()];
            $normalizers = [
                $c->get(HasPasswordNormalizer::class),
                new UidNormalizer([
                    UidNormalizer::NORMALIZATION_FORMAT_KEY => UidNormalizer::NORMALIZATION_FORMAT_RFC4122,
                ]),
                new DateTimeNormalizer(), // Default RFC3339 is fine.
                new PropertyNormalizer(), // ObjectNormalizer tried to do more "magic" than was helpful for us!
            ];

            return new Serializer($normalizers, $encoders);
        },

        Client\Stripe::class => static function (ContainerInterface $c): Client\Stripe {
            // Both hardcoding the version and using library default - see discussion at
            // https://github.com/thebiggive/matchbot/pull/927/files/5fa930f3eee3b0c919bcc1027319dc7ae9d0be05#diff-c4fef49ee08946228bb39de898c8770a1a6a8610fc281627541ec2e49c67b118
            \assert(ApiVersion::CURRENT === '2024-06-20');

            $settings = $c->get(SettingsInterface::class);
            \assert($settings instanceof SettingsInterface);

            /** @var array{apiKey: string} $stripeSettings */
            $stripeSettings = $settings->get('stripe');
            $stripeOptions = [
                'api_key' => $stripeSettings['apiKey'],
                'stripe_version' => ApiVersion::CURRENT,
            ];
            $stubbed = $settings->get('bypassPsp');
            \assert(is_bool($stubbed));

            return new Client\Stripe($stubbed, $stripeOptions);
        },

        ValidatorInterface::class => static function (): ValidatorInterface {
            return Validation::createValidatorBuilder()
                ->enableAttributeMapping()
                ->getValidator();
        },
    ]);
};
