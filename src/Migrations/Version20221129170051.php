<?php

declare(strict_types=1);

namespace BigGive\Identity\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20221129170051 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX `primary` ON PasswordResetToken');
        $this->addSql('ALTER TABLE PasswordResetToken ADD id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', ADD used DATETIME DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_F08775235CA2E8E5 ON PasswordResetToken (secret)');
        $this->addSql('ALTER TABLE PasswordResetToken ADD PRIMARY KEY (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_F08775235CA2E8E5 ON PasswordResetToken');
        $this->addSql('DROP INDEX `PRIMARY` ON PasswordResetToken');
        $this->addSql('ALTER TABLE PasswordResetToken DROP id, DROP used');
        $this->addSql('ALTER TABLE PasswordResetToken ADD PRIMARY KEY (secret)');
    }
}
