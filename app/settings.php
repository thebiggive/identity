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
            $doctrineConnectionOptions = [];
            if (getenv('APP_ENV') !== 'local') {
                $doctrineConnectionOptions[PDO::MYSQL_ATTR_SSL_CA] = dirname(__DIR__) . '/deploy/rds-ca-2019-root.pem';
            }

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
                'bypassPsp' => (
                    ((bool) getenv('BYPASS_PSP')) === true &&
                    getenv('APP_ENV') !== 'production'
                ),
                'displayErrorDetails' => true, // Should be set to false in production
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
                'recaptcha' => [
                    'bypass' => (
                        ((bool) getenv('RECAPTCHA_BYPASS')) === true &&
                        getenv('APP_ENV') !== 'production'
                    ),
                    'secret_key' => getenv('RECAPTCHA_SECRET_KEY'),
                ],
                'redis' => [
                    'host' => getenv('REDIS_HOST'),
                ],
                'stripe' => [
                    'apiKey' => getenv('STRIPE_SECRET_KEY'),
                    'apiVersion' => getenv('STRIPE_API_VERSION'),
                ],
                'accountManagement' => [
                    'baseUri' => getenv('ACCOUNT_MANAGEMENT_BASE_URI'),
                ],
            ]);
        }
    ]);
};
