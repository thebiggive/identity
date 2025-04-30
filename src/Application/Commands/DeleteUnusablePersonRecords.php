<?php

namespace BigGive\Identity\Application\Commands;

use BigGive\Identity\Application\Actions\Person\SetFirstPassword;
use BigGive\Identity\Application\Auth\Token;
use Doctrine\DBAL\Connection;
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
    public function __construct(private Connection $connection, private \DateTimeImmutable $now)
    {
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
            new \DateInterval('PT' . Token::COMPLETE_ACCOUNT_VALIDITY_PERIOD_SECONDS . 'S')
        )->format('c');

        $deletedCount = $this->connection->executeStatement(
            <<<'SQL'
                    DELETE FROM Person where password is null AND Person.updated_at <= :cutoff
                    ORDER BY updated_at
                    LIMIT 10000; -- we limit to deleting 10 000 at one go to avoid overloading the database.
                    SQL,
            ['cutoff' => $cuttOffTimeString]
        );

        $output->writeln("Deleted $deletedCount useless Person records from before $cuttOffTimeString.");

        return 0;
    }
}
