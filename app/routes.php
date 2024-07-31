<?php

declare(strict_types=1);

use BigGive\Identity\Application\Actions\ChangePasswordUsingToken;
use BigGive\Identity\Application\Actions\CreatePasswordResetToken;
use BigGive\Identity\Application\Actions\GetDonationFundsTransferInstructions;
use BigGive\Identity\Application\Actions\GetPasswordResetToken;
use BigGive\Identity\Application\Actions\Login;
use BigGive\Identity\Application\Actions\Person;
use BigGive\Identity\Application\Actions\Status;
use BigGive\Identity\Application\Middleware\CredentialsRecaptchaMiddleware;
use BigGive\Identity\Application\Middleware\PersonGetAuthMiddleware;
use BigGive\Identity\Application\Middleware\PersonPatchAuthMiddleware;
use BigGive\Identity\Application\Middleware\PersonRecaptchaMiddleware;
use BigGive\Identity\Application\Middleware\PlainRecaptchaMiddleware;
use LosMiddleware\RateLimit\RateLimitMiddleware;
use Middlewares\ClientIp;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Interfaces\RouteCollectorProxyInterface as Group;

return function (App $app) {
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

        $versionGroup->group('/people/{personId:[a-z0-9-]{36}}', function (Group $personGetGroup) {
            $personGetGroup->get('', Person\Get::class);
            $personGetGroup->get('/funding_instructions', GetDonationFundsTransferInstructions::class);
        })
            ->add(PersonGetAuthMiddleware::class);

        $versionGroup->post('/auth', Login::class)
            ->add(CredentialsRecaptchaMiddleware::class); // Runs last, after group's IP + rate limit middlewares.

        $versionGroup->post(
            '/password-reset-token',
            CreatePasswordResetToken::class
        )
            ->add(PlainRecaptchaMiddleware::class)
        ;

        $versionGroup->get('/password-reset-token/{base58Secret:[A-Za-z0-9-]{22}}', GetPasswordResetToken::class);

        $versionGroup->post('/change-forgotten-password', ChangePasswordUsingToken::class)
        ;
    })
        ->add($ipMiddleware)
        ->add(RateLimitMiddleware::class);

    // CORS Pre-Flight OPTIONS Request Handler
    $app->options(
        '/{routes:.+}',
        fn(RequestInterface $request, ResponseInterface $response, array $_args) => $response
    );
};
