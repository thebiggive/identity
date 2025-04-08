<?php

declare(strict_types=1);

namespace BigGive\Identity\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250408135601 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add DB table EmailVerificationToken';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE Person
                ADD email_address_verified TINYINT(1) NOT NULL
            SQL
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE EmailVerificationToken (
                id INT AUTO_INCREMENT NOT NULL,
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                email_address VARCHAR(255) NOT NULL,
                random_code VARCHAR(255) NOT NULL,
                PRIMARY KEY(id)
            ) 
            DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL
);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE EmailVerificationToken');

        $this->addSql(<<<'SQL'
            ALTER TABLE Person
                DROP email_address_verified
            SQL
        );
    }
}
