<?php

declare(strict_types=1);

namespace BigGive\Identity\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250411110319 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fix drift between doctrine definitions and schema';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE Person CHANGE email_address_verified email_address_verified DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE Person CHANGE email_address_verified email_address_verified DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }
}
