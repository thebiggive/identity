<?php

declare(strict_types=1);

namespace BigGive\Identity\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250408151924 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add index EmailVerificationToken (email_address)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX IDX_5D233019B08E074E ON EmailVerificationToken (email_address)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_5D233019B08E074E ON EmailVerificationToken');
    }
}
