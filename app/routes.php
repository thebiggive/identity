<?php

declare(strict_types=1);

use BigGive\Identity\Application\Actions\ChangePasswordUsingToken;
use BigGive\Identity\Application\Actions\CreatePasswordResetToken;
use BigGive\Identity\Application\Actions\EmailVerificationToken\GetEmailVerificationToken;
use BigGive\Identity\Application\Actions\GetDonationFundsTransferInstructions;
use BigGive\Identity\Application\Actions\GetPasswordResetToken;
use BigGive\Identity\Application\Actions\Login;
use BigGive\Identity\Application\Actions\Person;
use BigGive\Identity\Application\Actions\EmailVerificationToken;
use BigGive\Identity\Application\Actions\Status;
use BigGive\Identity\Application\Middleware\CredentialsCaptchaMiddleware;
use BigGive\Identity\Application\Middleware\PersonGetAuthMiddleware;
use BigGive\Identity\Application\Middleware\PersonPatchAuthMiddleware;
use BigGive\Identity\Application\Middleware\PersonCaptchaMiddleware;
use BigGive\Identity\Application\Middleware\PlainCaptchaMiddleware;
use LosMiddleware\RateLimit\RateLimitMiddleware;
use Middlewares\ClientIp;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Interfaces\RouteCollectorProxyInterface as Group;

return function (App $app) {
    $app->get('/ping', Status::class);

    // Provides real IP for logging etc.
    $ipMiddleware = getenv('APP_ENV') === 'local'
        ? new ClientIp()
        : (new ClientIp())->proxy([], ['X-Forwarded-For']);

    $app->group('/v1', function (Group $versionGroup) {
        $versionGroup->post('/people', Person\Create::class)
            ->add(PersonCaptchaMiddleware::class); // Runs last, after group's IP + rate limit middlewares.

        $versionGroup->put('/people/{personId:[a-z0-9-]{36}}', Person\Update::class)
            ->add(PersonPatchAuthMiddleware::class);

        // no special auth needed for this, as the route is all about authentication auth is handled by the
        // controller itself.
        $versionGroup->post(
            '/people/setFirstPassword',
            Person\SetFirstPassword::class
        );

        $versionGroup->group('/people/{personId:[a-z0-9-]{36}}', function (Group $personGetGroup) {
            $personGetGroup->get('', Person\Get::class);
            $personGetGroup->get('/funding_instructions', GetDonationFundsTransferInstructions::class);
        })
            ->add(PersonGetAuthMiddleware::class);

        $versionGroup->post('/auth', Login::class)
            ->add(CredentialsCaptchaMiddleware::class); // Runs last, after group's IP + rate limit middlewares.

        $versionGroup->post(
            '/password-reset-token',
            CreatePasswordResetToken::class
        )
            ->add(PlainCaptchaMiddleware::class)
        ;

        $versionGroup->get('/password-reset-token/{base58Secret:[A-Za-z0-9-]{22}}', GetPasswordResetToken::class);

        $versionGroup->post('/change-forgotten-password', ChangePasswordUsingToken::class)
        ;

        $versionGroup->get(
            '/emailVerificationToken/{secret:[0-9]{6}}/{personId:[a-z0-9-]{36}}',
            GetEmailVerificationToken::class
        );

        $versionGroup->post('/emailVerificationToken/', EmailVerificationToken\Create::class)
            ->add(PlainCaptchaMiddleware::class);
    })
        ->add($ipMiddleware)
        ->add(RateLimitMiddleware::class);

    // CORS Pre-Flight OPTIONS Request Handler
    $app->options(
        '/{routes:.+}',
        fn(RequestInterface $request, ResponseInterface $response, array $_args) => $response
    );
};
