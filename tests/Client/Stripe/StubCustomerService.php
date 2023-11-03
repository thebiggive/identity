<?php

namespace BigGive\Identity\Tests\Client\Stripe;

use Stripe\Customer;
use Stripe\StripeObject;

class StubCustomerService
{
    public function create(array $_params): Customer
    {
        throw new \Exception('test: STUB customer create!');

        $this->pause();

        /** @var string $customerJSON */
        $customerJSON = file_get_contents(
            dirname(__DIR__, 3) . '/MockStripeResponses/customer_no_credit.json'
        );
        /** @var array $customer */
        $customer = json_decode($customerJSON, true, 512, JSON_THROW_ON_ERROR);
        $customer['id'] = 'cus_' . bin2hex(random_bytes(12));

        $customer = StripeObject::constructFrom($customer);
        assert($customer instanceof Customer);

        return $customer;
    }

    /**
     * @see BigGive\Identity\Application\Actions\Person\Update
     * This is called to patch data but its result isn't used, so we may pause and return void.
     */
    public function update(string $_customerId, array $params): void
    {
        $this->pause();
    }

    /**
     * Sleep for a random time between 0.1 and 1 seconds. This is a guess, I've not checked the exact
     * live timings for Customer callouts.
     */
    private function pause(): void
    {
        usleep(random_int(100_000, 1_000_000));
    }
}
