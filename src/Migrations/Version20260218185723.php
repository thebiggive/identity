<?php

declare(strict_types=1);

namespace BigGive\Identity\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Update (remove) Doctrine ORM type comments; result of doctrine:migrate:diff after upgrade to v3.
 */
final class Version20260218185723 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Update (remove) Doctrine ORM type comments';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE EmailVerificationToken CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE PasswordResetToken CHANGE secret secret BINARY(16) NOT NULL, CHANGE person person BINARY(16) DEFAULT NULL, CHANGE id id BINARY(16) NOT NULL');
        $this->addSql('ALTER TABLE Person CHANGE id id BINARY(16) NOT NULL, CHANGE email_address_verified email_address_verified DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE EmailVerificationToken CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE PasswordResetToken CHANGE id id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE secret secret BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE person person BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE Person CHANGE id id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE email_address_verified email_address_verified DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }
}
