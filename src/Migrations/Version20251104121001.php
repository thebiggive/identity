<?php

declare(strict_types=1);

namespace BigGive\Identity\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * BG2-3001 Delete a donor account
 */
final class Version20251104121001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Delete a donor account';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<SQL
            DELETE FROM Person
            WHERE Person.id = UUID_TO_BIN('1ed6fde2-f191-689e-8d9c-6fb7d44da103')
            LIMIT 1
            SQL
        );
    }

    public function down(Schema $schema): void
    {
        // No un-patch
    }
}
