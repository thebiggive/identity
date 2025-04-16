<?php

declare(strict_types=1);

namespace BigGive\Identity\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250411105139 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Use timestamp instead of tinyint to record when email addresses were verified.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE Person CHANGE email_address_verified email_address_verified TINYINT(1) NULL');
        $this->addSql('UPDATE Person SET email_address_verified = null;');
        $this->addSql('ALTER TABLE Person CHANGE email_address_verified email_address_verified DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('UPDATE Person SET email_address_verified = null;');
        $this->addSql('ALTER TABLE Person CHANGE email_address_verified email_address_verified TINYINT(1) NOT NULL');
    }
}
