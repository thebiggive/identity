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
                'displayErrorDetails' => true, // Should be set to false in production
                'doctrine' => [
                    // if true, metadata caching is forcefully disabled
                    'dev_mode' => (getenv('APP_ENV') === 'local'),

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
                    'name' => 'slim-app',
                    'path' => isset($_ENV['docker']) ? 'php://stdout' : __DIR__ . '/../logs/app.log',
                    'level' => Logger::DEBUG,
                ],
            ]);
        }
    ]);
};
