<?php

declare(strict_types=1);

namespace BigGive\Identity\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261016154110 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add columns to person about last login';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE Person ADD lastLoginTime DATETIME DEFAULT NULL, ADD lastLoginIP VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE Person DROP lastLoginTime, DROP lastLoginIP');
    }
}
