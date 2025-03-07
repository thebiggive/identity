<?php

declare(strict_types=1);

use BigGive\Identity\Application\Settings\Settings;
use BigGive\Identity\Application\Settings\SettingsInterface;
use DI\ContainerBuilder;
use Monolog\Logger;

return function (ContainerBuilder $containerBuilder) {
    // Global Settings Object
    $containerBuilder->addDefinitions([
        SettingsInterface::class => function () {
            $isProduction = getenv('APP_ENV') === 'production';
            $isLoadTest = !$isProduction && isset($_SERVER['HTTP_X_IS_LOAD_TEST']);

            $doctrineConnectionOptions = [];
            if (getenv('APP_ENV') !== 'local') {
                $doctrineConnectionOptions[PDO::MYSQL_ATTR_SSL_CA]
                    = dirname(__DIR__) . '/deploy/rds-ca-eu-west-1-bundle.pem';
            }

            /**
             * @psalm-suppress RiskyTruthyFalsyComparison - hard to avoid when working with env variables.
             */
            return new Settings([
                'apiClient' => [
                    'global' => [
                        'timeout' => getenv('CLIENT_TIMEOUT'), // in seconds
                    ],
                    'mailer' => [
                        'baseUri' => getenv('MAILER_BASE_URI'),
                        'sendSecret' => getenv('MAILER_SEND_SECRET'),
                    ],
                ],
                'appEnv' => getenv('APP_ENV'),
                'bypassPsp' => $isLoadTest,
                'displayErrorDetails' => ! $isProduction,
                'doctrine' => [
                    // if true, metadata caching is forcefully disabled
                    'dev_mode' => in_array(getenv('APP_ENV'), ['local', 'test'], true),

                    'cache_dir' => __DIR__ . '/../var/doctrine',
                    'metadata_dirs' => [__DIR__ . '/../src/Domain'],

                    'connection' => [
                        'driver' => 'pdo_mysql',
                        'host' => getenv('MYSQL_HOST'),
                        'port' => 3306,
                        'dbname' => getenv('MYSQL_SCHEMA'),
                        'user' => getenv('MYSQL_USER'),
                        'password' => getenv('MYSQL_PASSWORD'),
                        'charset' => 'utf8mb4',
                        'default_table_options' => [
                            'collate' => 'utf8mb4_unicode_ci',
                        ],
                        'options' => $doctrineConnectionOptions,
                    ],
                ],
                'logError'            => false,
                'logErrorDetails'     => false,
                'logger' => [
                    'name' => 'identity',
                    'path' => 'php://stdout',
                    'level' => getenv('APP_ENV') === 'local' ? Logger::DEBUG : Logger::INFO,
                ],
                'los_rate_limit' => [
                    // Dynamic so we can increase it for load tests or as needed based on observed
                    // Production behaviour.
                    'ip_max_requests'   => (int) (getenv('MAX_CREATES_PER_IP_PER_5M') ?: '1'),
                    'ip_reset_time'     => 300, // 5 minutes
                    // All non-local envs, including 'test', assume ALB-style forwarded headers will be used.
                    'prefer_forwarded' => getenv('APP_ENV') !== 'local',
                    'trust_forwarded' => getenv('APP_ENV') !== 'local',
                    'forwarded_headers_allowed' => [
                        'X-Forwarded-For',
                    ],
                    'hash_ips' => true, // Required for Redis storage of IPv6 addresses.
                ],
                'friendly_captcha' => [
                    'api_key' => getenv('FRIENDLY_CAPTCHA_SECRET_KEY'),
                    'site_key' => getenv('FRIENDLY_CAPTCHA_SITE_KEY'),
                    'bypass' => $isLoadTest,
                ],
                'redis' => [
                    'host' => getenv('REDIS_HOST'),
                ],
                'stripe' => [
                    'apiKey' => getenv('STRIPE_SECRET_KEY'),
                ],
                'accountManagement' => [
                    'baseUri' => getenv('ACCOUNT_MANAGEMENT_BASE_URI'),
                ],
                'messenger' => [
                    // Outbound uses SQS in deployed AWS environments and Redis locally.
                    'outbound_dsn' => getenv('MESSENGER_OUTBOUND_TRANSPORT_DSN'),
                ],
            ]);
        }
    ]);
};
