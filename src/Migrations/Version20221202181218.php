<?php

declare(strict_types=1);

namespace BigGive\Identity\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20221202181218 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE PasswordResetToken DROP INDEX UNIQ_F087752334DCD176, ADD INDEX IDX_F087752334DCD176 (person)');
        $this->addSql('ALTER TABLE PasswordResetToken CHANGE person person BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE PasswordResetToken ADD CONSTRAINT FK_F087752334DCD176 FOREIGN KEY (person) REFERENCES Person (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE PasswordResetToken DROP INDEX IDX_F087752334DCD176, ADD UNIQUE INDEX UNIQ_F087752334DCD176 (person)');
        $this->addSql('ALTER TABLE PasswordResetToken DROP FOREIGN KEY FK_F087752334DCD176');
        $this->addSql('ALTER TABLE PasswordResetToken CHANGE person person BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\'');
    }
}
