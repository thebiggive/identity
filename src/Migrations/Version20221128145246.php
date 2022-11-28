<?php

declare(strict_types=1);

namespace BigGive\Identity\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use phpDocumentor\Reflection\Types\This;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20221128145246 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create password reset token table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<SQL
            create table PasswordResetToken
            (
                secret BINARY(16) not null comment '(DC2Type:uuid)' primary key,
                person_id BINARY(16) not null,
                created_at DATETIME NOT NULL DEFAULT NOW(),
                updated_at DATETIME NOT NULL DEFAULT NOW() ON UPDATE now(),
                used             DATETIME default null
            );
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE PasswordResetToken;');
    }
}
