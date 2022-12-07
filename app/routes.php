<?php

declare(strict_types=1);

use BigGive\Identity\Application\Actions\ChangePasswordUsingToken;
use BigGive\Identity\Application\Actions\CreatePasswordResetToken;
use BigGive\Identity\Application\Actions\GetCreditFundingInstructions;
use BigGive\Identity\Application\Actions\Login;
use BigGive\Identity\Application\Actions\Person;
use BigGive\Identity\Application\Actions\Status;
use BigGive\Identity\Application\Middleware\CredentialsRecaptchaMiddleware;
use BigGive\Identity\Application\Middleware\PersonGetAuthMiddleware;
use BigGive\Identity\Application\Middleware\PersonPatchAuthMiddleware;
use BigGive\Identity\Application\Middleware\PersonRecaptchaMiddleware;
use LosMiddleware\RateLimit\RateLimitMiddleware;
use Middlewares\ClientIp;
use Psr\Http\Message\ServerRequestInterface as Request;
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
            $personGetGroup->get('/funding_instructions', GetCreditFundingInstructions::class);
        })
            ->add(PersonGetAuthMiddleware::class);

        $versionGroup->post('/auth', Login::class)
            ->add(CredentialsRecaptchaMiddleware::class); // Runs last, after group's IP + rate limit middlewares.

        $versionGroup->post(
            '/password-reset-token',
            CreatePasswordResetToken::class
        ); // @todo probably should put  recapcha on this. Also needs rate limiting.

        $versionGroup->post('/change-forgotten-password', ChangePasswordUsingToken::class);
    })
        ->add($ipMiddleware)
        ->add(RateLimitMiddleware::class);

    // CORS Pre-Flight OPTIONS Request Handler
    $app->options('/{routes:.+}', function ($request, $response, $args) {
        return $response;
    });

    $app->add(function (Request $request, $handler) {
        $response = $handler->handle($request);

        $givenOrigin = $request->getHeaderLine('Origin');
        $corsAllowedOrigin = 'https://donate.thebiggive.org.uk';
        $corsAllowedOrigins = [
            'http://localhost:4000', // Local via Docker SSR
            'http://localhost:4200', // Local via native `ng serve`
            'https://localhost:4200', // Local via native `ng serve --ssl`
            'https://donate-ecs-staging.thebiggivetest.org.uk', // ECS staging direct
            'https://donate-staging.thebiggivetest.org.uk', // ECS + S3 staging via CloudFront
            'https://donate-ecs-regression.thebiggivetest.org.uk', // ECS regression direct
            'https://donate-regression.thebiggivetest.org.uk', // ECS + S3 regression via CloudFront
            'https://donate-ecs-production.thebiggive.org.uk', // ECS production direct
            'https://donate-production.thebiggive.org.uk', // ECS + S3 production via CloudFront
            'https://donate.thebiggive.org.uk', // ECS + S3 production via CloudFront, short alias
            'https://thebiggive.global', // ECS + S3 production via CloudFront, temporary global alias
            'https://thebiggive.com', // ECS + S3 production via CloudFront, global alias
            'https://thebiggive.org', // ECS + S3 production via CloudFront, likely future global alias
        ];
        if (!empty($givenOrigin) && in_array($givenOrigin, $corsAllowedOrigins, true)) {
            $corsAllowedOrigin = $givenOrigin;
        }

        // Basic approach based on https://www.slimframework.com/docs/v4/cookbook/enable-cors.html
        // - adapted to allow for multiple potential origins per-Identity instance.
        return $response
            ->withHeader('Access-Control-Allow-Origin', $corsAllowedOrigin)
            ->withHeader(
                'Access-Control-Allow-Headers',
                'Accept, Authorization, Content-Type, Origin, X-Requested-With, X-Tbg-Auth, x-captcha-code'
            )
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
            ->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->withAddedHeader('Cache-Control', 'post-check=0, pre-check=0')
            ->withHeader('Pragma', 'no-cache');
    });
};
