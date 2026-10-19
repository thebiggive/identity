<?php

declare(strict_types=1);

namespace BigGive\Identity\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261019163000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add lastFailedLoginTime and failedLoginAttempts columns to person for lockout policy';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE Person ADD lastFailedLoginTime DATETIME DEFAULT NULL, ADD failedLoginAttempts INT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE Person DROP lastFailedLoginTime, DROP failedLoginAttempts');
    }
}
