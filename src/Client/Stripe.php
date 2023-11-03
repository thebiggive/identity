<?php

declare(strict_types=1);

namespace BigGive\Identity\Client;

use BigGive\Identity\Tests\Client\Stripe\StubCustomerService;
use Stripe\StripeClient;

class Stripe extends StripeClient
{
    public function __construct(bool $stubbed, array $stripeOptions)
    {
        if ($stubbed && getenv('APP_ENV') === 'production') {
            throw new \LogicException('Cannot stub out Stripe in production');
        }

        if (!$stubbed) {
            parent::__construct($stripeOptions);
            return;
        }

        // Else, fake everything we must for load tests. "real" Stripe [with test key since the above guards should
        // preclude this being reached in Production] may still be called for anything not explicitly stubbed, but
        // currently that will fail because we never pass options like key to the constructor in stub mode.
        $this->customers = new StubCustomerService();
    }

    public function __get($name)
    {
        error_log('MAGIC GET: ' . $name);

        return $this->$name;
    }
}
