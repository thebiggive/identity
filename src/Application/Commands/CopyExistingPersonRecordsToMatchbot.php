<?php

namespace BigGive\Identity\Application\Commands;

use BigGive\Identity\Application\Auth\Token;
use BigGive\Identity\Repository\PersonRepository;
use Doctrine\DBAL\Connection;
use Monolog\DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\RoutableMessageBus;

/**
 * Command only needed temporarily, to send copies of all Person records that were last updated before
 * we started a live sync to matchbot. Once the job is complete this can be deleted.
 */
#[AsCommand(name: 'identity:copy-existing-person-records-to-matchbot')]
class CopyExistingPersonRecordsToMatchbot extends Command
{
    /** @psalm-suppress PossiblyUnusedMethod */
    public function __construct(
        private Connection $connection,
        private RoutableMessageBus $bus,
        private PersonRepository $personRepository,
        private LoggerInterface $logger
    ) {
        parent::__construct();
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $personsAlreadySyncedUpTo = new \DateTimeImmutable((string) $this->connection->fetchOne(
            "SELECT value from `KeyValue` where `Key` = 'PersonRecordsSentToMatchBotTo'"
        ));

        $batchOfPersons = $this->personRepository->findOldestPersonRecordsRequiringSyncToMatchbot(
            $personsAlreadySyncedUpTo
        );

        if ($batchOfPersons === []) {
            // Sadly I think this condition can never be met - the final batch will have one person not zero, because
            // we can't assume that two different people will have different updated_at dates, and id doesn't increase,
            // and its probably not worth persisting a record of the last one done. When we notice its doing batches of
            // 1 we can stop running and delete this command.

            $output->writeln("Empty batch of persons - nothing to do");
            return 0;
        }

        foreach ($batchOfPersons as $person) {
            $personMessage = $person->toMatchBotSummaryMessage();

            $this->logger->info(sprintf("Will dispatch message about person %s", $personMessage->id));
            $this->bus->dispatch(new Envelope($personMessage));

            $personsNowSyncedUpTo = DateTimeImmutable::createFromInterface($person->updated_at);
        }

        $this->connection->executeStatement(
            "UPDATE KeyValue SET value = ? where `key` = 'PersonRecordsSentToMatchBotTo'",
            [$personsNowSyncedUpTo->format('c')]
        );

        $count = count($batchOfPersons);

        $output->writeln("Sent records of $count person(s) to matchbot, " .
            "updated dates between " . $personsAlreadySyncedUpTo->format('c') .
            " and " . $personsNowSyncedUpTo->format('c'));

        return 0;
    }
}
