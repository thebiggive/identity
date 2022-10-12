<?php

declare(strict_types=1);

use BigGive\Identity\Application\Actions\Login;
use BigGive\Identity\Application\Actions\Person;
use BigGive\Identity\Application\Actions\Status;
use BigGive\Identity\Application\Middleware\CredentialsRecaptchaMiddleware;
use BigGive\Identity\Application\Middleware\PersonGetAuthMiddleware;
use BigGive\Identity\Application\Middleware\PersonPatchAuthMiddleware;
use BigGive\Identity\Application\Middleware\PersonRecaptchaMiddleware;
use LosMiddleware\RateLimit\RateLimitMiddleware;
use Middlewares\ClientIp;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Slim\Interfaces\RouteCollectorProxyInterface as Group;

return function (App $app) {
    $app->options('/{routes:.*}', function (Request $request, Response $response) {
        // CORS Pre-Flight OPTIONS Request Handler
        return $response;
    });

    $app->get('/ping', Status::class);

    // Provides real IP for reCAPTCHA
    $ipMiddleware = getenv('APP_ENV') === 'local'
        ? new ClientIp()
        : (new ClientIp())->proxy([], ['X-Forwarded-For']);

    $app->group('/v1', function (Group $versionGroup) {
        $versionGroup->post('/people', Person\Create::class)
            ->add(PersonRecaptchaMiddleware::class); // Runs last, after group's IP + rate limit middlewares.

        $versionGroup->put('/people/{personId:[a-z0-9-]{36}}', Person\Update::class)
            ->add(PersonPatchAuthMiddleware::class);

        $versionGroup->get('/people/{personId:[a-z0-9-]{36}}', Person\Get::class)
            ->add(PersonGetAuthMiddleware::class);

        $versionGroup->post('/auth', Login::class)
            ->add(CredentialsRecaptchaMiddleware::class); // Runs last, after group's IP + rate limit middlewares.
    })
        ->add($ipMiddleware)
        ->add(RateLimitMiddleware::class);
};
