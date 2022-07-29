<?php

declare(strict_types=1);

namespace BigGive\Identity\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Make timestamps casing consistent with other `snake_case` fields.
 */
final class Version20220729164443 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Make timestamps' casing consistent with other fields";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE PaymentMethod ADD created_at DATETIME NOT NULL, ADD updated_at DATETIME NOT NULL, DROP createdAt, DROP updatedAt');
        $this->addSql('ALTER TABLE Person ADD created_at DATETIME NOT NULL, ADD updated_at DATETIME NOT NULL, DROP createdAt, DROP updatedAt');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE PaymentMethod ADD createdAt DATETIME NOT NULL, ADD updatedAt DATETIME NOT NULL, DROP created_at, DROP updated_at');
        $this->addSql('ALTER TABLE Person ADD createdAt DATETIME NOT NULL, ADD updatedAt DATETIME NOT NULL, DROP created_at, DROP updated_at');
    }
}
