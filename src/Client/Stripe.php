<?php

declare(strict_types=1);

namespace BigGive\Identity\Client;

use BigGive\Identity\LoadTestServices\Stripe\StubCustomerService;
use Stripe\Service\CustomerService;
use Stripe\Service\PaymentIntentService;
use Stripe\Service\PaymentMethodService;
use Stripe\StripeClient;

class Stripe
{
    public CustomerService|StubCustomerService $customers;
    public ?PaymentIntentService $paymentIntents = null;

    /**
     * @psalm-suppress PropertyNotSetInConstructor - will crash if used in stub mode, but we can fix that when it
     * happens.
     */
    public PaymentMethodService $paymentMethods;

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

        // Map other services to the real client, only in non-stub mode. No load tests
        // and therefore no stubbed Stripe calls use these as yet.
        if (!$stubbed) {
            $this->paymentIntents = $stripeNativeClient->paymentIntents;
            $this->paymentMethods = $stripeNativeClient->paymentMethods;
        }

        // Any other service will crash in either mode, as we don't implement a magic
        // __get.
    }
}
