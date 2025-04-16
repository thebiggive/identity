<?php

declare(strict_types=1);

namespace BigGive\Identity\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250411111120 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fix doctrine mappings drift again';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE Person CHANGE email_address_verified email_address_verified DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE Person CHANGE email_address_verified email_address_verified DATETIME DEFAULT NULL');
    }
}
