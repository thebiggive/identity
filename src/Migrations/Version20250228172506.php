<?php

declare(strict_types=1);

namespace BigGive\Identity\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250228172506 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop KeyValue table that has been unused since commit 3870ec6b35a. Migration generated with newly working diff command.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TABLE KeyValue');
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE KeyValue (
    `key` VARCHAR(32) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, 
    value VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`)
    DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB COMMENT = \'\' '
        );
    }
}
