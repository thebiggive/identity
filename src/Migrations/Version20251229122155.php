<?php

declare(strict_types=1);

namespace BigGive\Identity\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251229122155 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Auto delete password reset tokens for any deleted user account';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE PasswordResetToken DROP FOREIGN KEY FK_F087752334DCD176');
        $this->addSql('ALTER TABLE PasswordResetToken ADD CONSTRAINT FK_F087752334DCD176 FOREIGN KEY (person) REFERENCES Person (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE PasswordResetToken DROP FOREIGN KEY FK_F087752334DCD176');
        $this->addSql('ALTER TABLE PasswordResetToken ADD CONSTRAINT FK_F087752334DCD176 FOREIGN KEY (person) REFERENCES Person (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
    }
}
