<?php

declare(strict_types=1);

namespace BigGive\Identity\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * ID-18 – persist password hash.
 */
final class Version20220920100020 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add password hash';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE Person ADD password VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE Person DROP password');
    }
}
