<?php

declare(strict_types=1);

use Tbg\Identity\Application\Actions\Status;
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

//    $app->post('/people', CreatePerson::class)
//        ->add(RecaptchaMiddleware::class) // Runs last
//        ->add($ipMiddleware)
//        ->add(RateLimitMiddleware::class);

//    $app->post('auth', Login::class);

//    $app->group('/people', function (Group $peopleGroup) {
//        $peopleGroup->get('/{id}', ViewPersonAction::class);
//
//        $peopleGroup->group('/payment_methods', function (Group $paymentMethodsGroup) {
//            $paymentMethodsGroup->post('', CreatePaymentMethod::class);
//            $paymentMethodsGroup->post('/{id}', DeletePaymentMethod::class);
//        });
//    })
//        ->add(IdentityAuthMiddleware::class);
};
