<?php

declare(strict_types=1);

namespace BigGive\Identity\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Generated by running ./vendor/bin/doctrine-migrations diff
 */
final class Version20221129150137 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Update DB schema to match entity definition for password reset token';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE PasswordResetToken DROP used, CHANGE person_id person_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_F0877523217BBB47 ON PasswordResetToken (person_id)');
        $this->addSql('CREATE INDEX secret ON PasswordResetToken (secret)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_F0877523217BBB47 ON PasswordResetToken');
        $this->addSql('DROP INDEX secret ON PasswordResetToken');
        $this->addSql('ALTER TABLE PasswordResetToken ADD used DATETIME DEFAULT NULL, CHANGE person_id person_id BINARY(16) DEFAULT NULL, CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE updated_at updated_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
    }
}