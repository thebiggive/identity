<?php

declare(strict_types=1);

namespace BigGive\Identity\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Make `email_address` consistent with other `snake_case` fields.
 */
final class Version20220729172829 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fix email_address casing';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_3370D440465A626E ON Person');
        $this->addSql('ALTER TABLE Person CHANGE emailAddress email_address VARCHAR(255) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_3370D440B08E074E ON Person (email_address)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_3370D440B08E074E ON Person');
        $this->addSql('ALTER TABLE Person CHANGE email_address emailAddress VARCHAR(255) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_3370D440465A626E ON Person (emailAddress)');
    }
}
