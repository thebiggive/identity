<?php

declare(strict_types=1);

namespace BigGive\Identity\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260204173517 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Change password on account';
    }

    public function up(Schema $schema): void
    {
        // hash is of random long unguessable password
        $this->addSql(<<<'SQL'
            UPDATE Person SET password = '$2y$12$okBWNtVuLtilSsTuomaMVuRVUA0OQeIfbwyIdTN6dzFouFnWm6O7i'
            WHERE Person.id = UUID_TO_BIN('1f0fe2c4-2381-65d0-a6bc-cd6031a2e8e8')
            LIMIT 1
            SQL
        );
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException();
    }
}
