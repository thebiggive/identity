<?php

declare(strict_types=1);

namespace BigGive\Identity\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * ID-18 – make remaining non-anonymous Person fields nullable.
 */
final class Version20220913140011 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make remaining non-anonymous Person fields nullable';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE Person CHANGE first_name first_name VARCHAR(255) DEFAULT NULL, CHANGE last_name last_name VARCHAR(255) DEFAULT NULL, CHANGE email_address email_address VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE Person CHANGE first_name first_name VARCHAR(255) NOT NULL, CHANGE last_name last_name VARCHAR(255) NOT NULL, CHANGE email_address email_address VARCHAR(255) NOT NULL');
    }
}
