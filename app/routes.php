<?php

declare(strict_types=1);

use BigGive\Identity\Application\Actions\Status;
use BigGive\Identity\Application\Actions\CreatePerson;
use BigGive\Identity\Application\Middleware\RecaptchaMiddleware;
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
        
        // Current unauthenticated endpoint in the `/v1` group. Middlewares run in reverse
        // order when chained this way – so we check rate limits first, then get the real
        // IP in an attribute for reCAPTCHA sending, then check the captcha.
        $versionGroup->get('/people', CreatePerson::class);
            // ->add(RecaptchaMiddleware::class) // Runs last
            // ->add($ipMiddleware)
            // ->add(RateLimitMiddleware::class);

//    $app->post('auth', Login::class);

//    $app->group('/people', function (Group $peopleGroup) {
//        $peopleGroup->get('/{id}', GetPersonAction::class);
//
//        $peopleGroup->group('/payment_methods', function (Group $paymentMethodsGroup) {
//            $paymentMethodsGroup->post('', CreatePaymentMethod::class);
//            $paymentMethodsGroup->post('/{id}', DeletePaymentMethod::class);
//        });
//    })
//        ->add(IdentityAuthMiddleware::class);
    });
};
