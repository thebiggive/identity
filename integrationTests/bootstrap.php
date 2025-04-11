<?php

require __DIR__ . '/../vendor/autoload.php';

if (! in_array(getenv('APP_ENV'), ['local', 'test'])) {
    throw new \Exception("Don't run integration tests in live!");
}