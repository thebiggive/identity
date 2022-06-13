<?php

declare(strict_types=1);

use BigGive\Identity\Application\Middleware\SessionMiddleware;
use Slim\App;

return function (App $app) {
    $app->add(SessionMiddleware::class);
};
