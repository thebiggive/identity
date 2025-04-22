<?php

namespace BigGive\Identity\Application\Commands;

use BigGive\Identity\Client\Stripe;
use BigGive\Identity\Repository\PersonRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'identity:patch-missing-stripe-customers')]
class PatchMissingStripeCustomers extends Command
{
    public function __construct(
        private PersonRepository $personRepository,
        private Stripe $stripe,
    ) {
        parent::__construct();
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $people = $this->personRepository->findByNullStripeIdFrom16April();
        $output->writeln('Found ' . count($people) . ' people to potentially patch');

        foreach ($people as $person) {
            $customer = $this->stripe->customers->create($person->getStripeCustomerParams());

            $person->setStripeCustomerId($customer->id);
            $this->personRepository->persist(person: $person, skipMatchbotSync: false);

            $output->writeln("Patched person {$person->getId()} with Stripe customer ID {$customer->id}");
        }

        $output->writeln('Done patching');

        return 0;
    }
}
