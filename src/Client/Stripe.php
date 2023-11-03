<?php

declare(strict_types=1);

namespace BigGive\Identity\Client;

use BigGive\Identity\LoadTestServices\Stripe\StubCustomerService;
use Stripe\Service\CustomerService;
use Stripe\StripeClient;

/**
 * @property ?StubCustomerService|CustomerService $customers
 */
class Stripe
{
    private ?StripeClient $stripeNativeClient = null;

    public function __construct(bool $stubbed, array $stripeOptions)
    {
        if ($stubbed && getenv('APP_ENV') === 'production') {
            throw new \LogicException('Cannot stub out Stripe in production');
        }

        if (!$stubbed) {
            $this->stripeNativeClient = new StripeClient($stripeOptions);
            return;
        }

        // Else, fake everything we must for load tests. Anything not stubbed explicitly will crash in
        // stub mode.
        $this->customers = new StubCustomerService();
    }

    public function __get(string $name)
    {
        return $this->stripeNativeClient->{$name};
    }
}
