<?php

declare(strict_types=1);

namespace BigGive\Identity\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * BG2-2992 Delete a donor account
 */
final class Version20251015145723 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Delete a donor account';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<SQL
            DELETE FROM Person
            WHERE Person.id = UUID_TO_BIN('1f0a994b-e5ca-6b68-b997-a9c1def9d8f6')
            LIMIT 1
            SQL
        );
    }

    public function down(Schema $schema): void
    {
        // No un-patch
    }
}
