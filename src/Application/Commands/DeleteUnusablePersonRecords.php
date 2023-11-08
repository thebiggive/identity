<?php

namespace BigGive\Identity\Application\Commands;

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
#[AsCommand(name: 'identity:populate-test-users')]
class DeleteUnusablePersonRecords extends Command
{
    public function __construct(private Connection $connection, private \DateTimeImmutable $now)
    {
        parent::__construct();
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        /**
         * Passwordless person records really become useless IMHO imediatly when the 8 hour validity expires. But
         * for now lets only delete them when they are four times older that, i.e. after 32 hours, just in case
         * we need to see them in the DB for support and diagnostic purposes.
         */
        $cutoffTimeIntervalSeconds = Token::VALIDITY_PERIOD_SECONDS * 4;

        $cuttOffTimeString = $this->now->sub(new \DateInterval('PT' . $cutoffTimeIntervalSeconds . 'S'))
            ->format('c');

        $this->connection->executeStatement(
            <<<'SQL'
                    DELETE FROM Person where password is null AND Person.updated_at <= :cutoff
                    SQL,
            ['cutoff' => $cuttOffTimeString]
        );

        return 0;
    }
}
