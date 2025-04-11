<?php

namespace BigGive\Identity\IntegrationTests;

use BigGive\Identity\Application\Commands\DeleteUnusablePersonRecords;
use BigGive\Identity\Domain\Person;
use BigGive\Identity\Repository\PersonRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Uid\UuidV4;

class DeleteUnusablePersonRecordsTest extends IntegrationTest
{
    private PersonRepository $personRepository;
    private Connection $connection;
    private \DateTimeImmutable $now;
    private UuidV4 $personId;
    private string $randomEmail;
    private CommandTester $commandTester;

    public function setUp(): void
    {
        parent::setUp();
        $this->now = new \DateTimeImmutable('2020-11-30 12:00:00');

        $this->personRepository = $this->getService(PersonRepository::class);
        $this->connection = $this->getService(Connection::class);
        $this->personId = \Symfony\Component\Uid\Uuid::v4();
        $this->randomEmail = 'test_delete_unusable' . random_int(1, 1_000_000) . '@thebiggivetest.co.uk';
        $this->commandTester = new CommandTester(new DeleteUnusablePersonRecords($this->connection, $this->now));
    }

    public function testItDeletesA32HourOldPasswordlessPerson(): void
    {
        $this->addPersonToDatabase(createdAt: '2020-11-29 04:00:00', withPassword: false);

        $this->commandTester->execute([]);

        $this->assertNull($this->personRepository->find($this->personId));
    }

    public function testItDoesNotDeletesALessThan32HourOldPasswordlessPerson(): void
    {
        $this->addPersonToDatabase(createdAt: '2020-11-29 04:00:01', withPassword: false);

        $this->commandTester->execute([]);

        $this->assertNotNull($this->personRepository->find($this->personId));
    }

    public function testItDoesNotDeleteA32HourOldPasswordHavingPerson(): void
    {
        $this->addPersonToDatabase(createdAt: '2020-11-29 04:00:00', withPassword: true);

        $this->commandTester->execute([]);

        $this->assertNotNull($this->personRepository->find($this->personId));
    }

    public function testItDoesNotDeletesALessThan32HourOldPasswordHavingPerson(): void
    {
        $this->addPersonToDatabase(createdAt: '2020-11-29 04:00:01', withPassword: true);

        $this->commandTester->execute([]);

        $this->assertNotNull($this->personRepository->find($this->personId));
    }

    public function addPersonToDatabase(string $createdAt, bool $withPassword): void
    {
        $password = $withPassword ? '\'$2y$10$4..1A/6AYEi7aL1sKz1M3OPKKSZYBGXXCoH7mL88ZwzA4KO.c9asK\'' : 'null';

        $this->connection->executeStatement(
            <<<SQL
            INSERT INTO Person 
                (
                    id, first_name, last_name, email_address, created_at, updated_at,
                    password, email_address_verified
                ) VALUES
                (
                    :id, 'first', 'last', '$this->randomEmail', '$createdAt', '$createdAt',
                 $password, null
                )
            SQL,
            ['id' => $this->personId->toBinary()]
        );
    }

    public function tearDown(): void
    {
        $this->connection->executeStatement(
            'DELETE FROM Person where Person.email_address like \'test_delete_unusable%\';'
        );
    }
}
