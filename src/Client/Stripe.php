<?php

declare(strict_types=1);

namespace BigGive\Identity\Client;

use BigGive\Identity\LoadTestServices\Stripe\StubCustomerService;
use Stripe\Service\CustomerService;
use Stripe\Service\PaymentIntentService;
use Stripe\StripeClient;

class Stripe
{
    public CustomerService|StubCustomerService $customers;
    public PaymentIntentService $paymentIntents;

    public function __construct(bool $stubbed, array $stripeOptions)
    {
        if ($stubbed && getenv('APP_ENV') === 'production') {
            throw new \LogicException('Cannot stub out Stripe in production');
        }

        $stripeNativeClient = new StripeClient($stripeOptions);

        // Fake everything we must for load tests.
        $this->customers = $stubbed
            ? new StubCustomerService()
            : $stripeNativeClient->customers;

        // Defer to the real service in both modes, for services we use but don't
        // think need stubbing.
        $this->paymentIntents = $stripeNativeClient->paymentIntents;

        // Any other service will crash in either mode, as we don't implement a magic
        // __get.
    }
}
