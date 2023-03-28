<?php

use BigGive\Identity\IntegrationTests\IntegrationTest;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

if (! in_array(getenv('APP_ENV'), ['local', 'test'])) {
    throw new \Exception("Don't run integration tests in live!");
}

$container = require __DIR__ . '/../bootstrap.php';
IntegrationTest::setContainer($container);

// Instantiate the app
AppFactory::setContainer($container);
$app = AppFactory::create();

// Register routes
$routes = require __DIR__ . '/../app/routes.php';
$routes($app);

IntegrationTest::setApp($app);
