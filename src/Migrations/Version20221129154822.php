<?php

declare(strict_types=1);

namespace BigGive\Identity\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration using doctrine diff
 */
final class Version20221129154822 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create PasswordResetToken';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE PasswordResetToken (secret BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', person BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_F087752334DCD176 (person), INDEX secret (secret), PRIMARY KEY(secret)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE PasswordResetToken');
    }
}
