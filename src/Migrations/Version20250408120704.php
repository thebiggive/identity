<?php

declare(strict_types=1);

namespace BigGive\Identity\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250408120704 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE Person
                ADD emailAddressVerified TINYINT(1) NOT NULL,
                ADD emailAddressVerificationCode VARCHAR(255) DEFAULT NULL,
                ADD emailAddressVerificationCodeGeneratedAt DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'
            SQL
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE Person
                DROP emailAddressVerified,
                DROP emailAddressVerificationCode,
                DROP emailAddressVerificationCodeGeneratedAt
            SQL
        );
    }
}
