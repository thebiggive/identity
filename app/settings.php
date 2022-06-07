<?php

declare(strict_types=1);

use Tbg\Identity\Application\Settings\Settings;
use Tbg\Identity\Application\Settings\SettingsInterface;
use DI\ContainerBuilder;
use Monolog\Logger;

return function (ContainerBuilder $containerBuilder) {
    $doctrineConnectionOptions = [];
    if (getenv('APP_ENV') !== 'local') {
        $doctrineConnectionOptions[PDO::MYSQL_ATTR_SSL_CA] = dirname(__DIR__) . '/deploy/rds-ca-2019-root.pem';
    }

    // Global Settings Object
    $containerBuilder->addDefinitions([
        SettingsInterface::class => function () {
            return new Settings([
                'displayErrorDetails' => true, // Should be set to false in production
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
