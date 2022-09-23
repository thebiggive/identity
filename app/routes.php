<?php

declare(strict_types=1);

use BigGive\Identity\Application\Actions\Login;
use BigGive\Identity\Application\Actions\Person;
use BigGive\Identity\Application\Actions\Status;
use BigGive\Identity\Application\Middleware\CredentialsRecaptchaMiddleware;
use BigGive\Identity\Application\Middleware\PersonManagementAuthMiddleware;
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

    $app->group('/v1', function (Group $versionGroup) {
        // Provides real IP for reCAPTCHA
        $ipMiddleware = getenv('APP_ENV') === 'local'
            ? new ClientIp()
            : (new ClientIp())->proxy([], ['X-Forwarded-For']);

        $versionGroup->post('/people', Person\Create::class)
            ->add(PersonRecaptchaMiddleware::class) // Runs last
            ->add($ipMiddleware)
            ->add(RateLimitMiddleware::class);

        $versionGroup->group('/people/{personId:[a-z0-9-]{36}}', function (Group $group) {
            $group->get('', Person\Get::class);
            $group->put('', Person\Update::class);
        })
            ->add(PersonManagementAuthMiddleware::class);

        $versionGroup->post('/auth', Login::class)
            ->add(CredentialsRecaptchaMiddleware::class) // Runs last
            ->add($ipMiddleware)
            ->add(RateLimitMiddleware::class);
    });
};
