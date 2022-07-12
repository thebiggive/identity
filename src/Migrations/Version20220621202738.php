<?php

declare(strict_types=1);

namespace BigGive\Identity\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * ID-2 – first ever schema.
 */
final class Version20220621202738 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Bootstrap: first attempt at a data structure';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE PaymentMethod (id INT UNSIGNED AUTO_INCREMENT NOT NULL, person_id INT UNSIGNED DEFAULT NULL, createdAt DATETIME NOT NULL, updatedAt DATETIME NOT NULL, INDEX IDX_37FAAE8D217BBB47 (person_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE Person (id INT UNSIGNED AUTO_INCREMENT NOT NULL, createdAt DATETIME NOT NULL, updatedAt DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE PaymentMethod ADD CONSTRAINT FK_37FAAE8D217BBB47 FOREIGN KEY (person_id) REFERENCES Person (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE PaymentMethod DROP FOREIGN KEY FK_37FAAE8D217BBB47');
        $this->addSql('DROP TABLE PaymentMethod');
        $this->addSql('DROP TABLE Person');
    }
}
