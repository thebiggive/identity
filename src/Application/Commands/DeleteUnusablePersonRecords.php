<?php

namespace BigGive\Identity\Application\Commands;

use Assert\Assertion;
use BigGive\Identity\Application\Actions\Person\SetFirstPassword;
use BigGive\Identity\Application\Auth\Token;
use BigGive\Identity\Application\Auth\TokenService;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Stripe\StripeClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * If a person does not have a saved password, and has been in the database long enough that we know their
 * JWT can't still be valid, then the record is useless so we delete it. They would need a new record next
 * time they come to the website anyway.
 */
#[AsCommand(name: 'identity:delete-unusable-person-records')]
class DeleteUnusablePersonRecords extends Command
{
    public function __construct(
        private Connection $connection,
        private \DateTimeImmutable $now,
        private StripeClient $stripeClient,
        private LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        /**
         * Passwordless person records become useless  when the 8 hour limit imposed by
         * {@see SetFirstPassword} expires - at that point they can't set a password and their guest session is
         * definitely expired.
         */
        $cuttOffTimeString = $this->now->sub(
            new \DateInterval('PT' . TokenService::COMPLETE_ACCOUNT_VALIDITY_PERIOD_SECONDS . 'S')
        )->format('c');

        $deletedCount = 0;

        $rows = $this->connection->executeQuery(
            <<<'SQL'
                    SELECT Person.id, Person.stripe_customer_id FROM Person 
                    where password is null AND 
                    Person.updated_at <= :cutoff
                    ORDER BY updated_at
                    LIMIT 1000;
                    SQL,
            ['cutoff' => $cuttOffTimeString]
        )->fetchAllAssociative();

        foreach ($rows as $row) {
            $stripeCustomerId = $row['stripe_customer_id'];
            \assert(\is_string($stripeCustomerId) || \is_null($stripeCustomerId));
            Assertion::nullOrNotEmpty($stripeCustomerId);

            $id = $row['id'];
            Assertion::string($id);

            if ($stripeCustomerId !== null) {
                $this->detatchAllPaymentCardsForCustomer($stripeCustomerId);
            }

            // check for password and limit below should not be necessary, as it's impossible to set a password
            // for the first time on account from before $cuttOffTimeString, and no two accounts can share an ID.
            //
            // Just added for extra safety.
            $rowsDeleted = $this->connection->executeStatement(
                <<<'SQL'
                DELETE FROM Person WHERE Person.id = ? AND 
                password is null 
                LIMIT 1
                SQL,
                [$id]
            );

            $deletedCount += $rowsDeleted;
        }

        $message = "Deleted $deletedCount useless Person records from before $cuttOffTimeString.";
        $output->writeln($message);
        $this->logger->info($message);

        return 0;
    }

    private function detatchAllPaymentCardsForCustomer(string $stripeCustomerId): void
    {
        // implementation copied from Matchbot's DeleteStalePaymentDetails which I'm planning to delete
        // in the next days. Not a problem if both systems are doing this for now.

        Assertion::notBlank($stripeCustomerId);
        Assertion::startsWith($stripeCustomerId, 'cus_');

        $iteratorPageSize = 100;

        $paymentMethods = $this->stripeClient->paymentMethods->all([
            'customer' => $stripeCustomerId,
            'type' => 'card',
            'limit' => $iteratorPageSize,
        ]);

        foreach ($paymentMethods->autoPagingIterator() as $paymentMethod) {
            $this->logger->info(sprintf(
                'Detaching payment method %s, previously of customer %s',
                $paymentMethod->id,
                $stripeCustomerId,
            ));

            $this->stripeClient->paymentMethods->detach($paymentMethod->id);
        }
    }
}
