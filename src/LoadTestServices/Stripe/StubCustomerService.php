<?php

namespace BigGive\Identity\LoadTestServices\Stripe;

use Stripe\Customer;

class StubCustomerService
{
    public function create(array $_params): Customer
    {
        $this->pause();

        /** @var string $customerJSON */
        $customerJSON = file_get_contents(
            dirname(__DIR__, 3) . '/tests/MockStripeResponses/customer_no_credit.json'
        );
        /** @var array $customer */
        $customer = json_decode($customerJSON, true, 512, JSON_THROW_ON_ERROR);
        $customer['id'] = 'cus_' . bin2hex(random_bytes(12));

        return Customer::constructFrom($customer);
    }

    public function retrieve(): Customer
    {
        return $this->create([]);
    }

    /**
     * For now, keep static analysis happy and our assumptions explicit by crashing
     * on attempts to use funding instructions while in load test stub mode. No load
     * tests are doing this for now.
     */
    public function createFundingInstructions(): void
    {
        throw new \LogicException('No stub createFundingInstructions()');
    }

    /**
     * @see BigGive\Identity\Application\Actions\Person\Update
     * This is called to patch data but its result isn't used, so we may pause and return void.
     */
    public function update(string $_customerId, array $_params): void
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
