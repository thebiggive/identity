<?php

declare(strict_types=1);

namespace BigGive\Identity\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260325120259 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Update password for one donor account';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'UPDATE Person SET password = \'$2y$12$pCV4SBUEed.RSRQjQC210.uQvfqp7WNB9e5Dq/e9FgaCLQIQfGN5i\' WHERE Person.id = uuid_to_bin(\'1f11e247-a67f-6d22-ad22-b7b635aa03ed\') LIMIT 1'
        );
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException();
    }
}
