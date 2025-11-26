<?php

declare(strict_types=1);

namespace BigGive\Identity\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251126144315 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Delete a donor account';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<SQL
            DELETE FROM Person
            WHERE Person.id = UUID_TO_BIN('1f02115c-c44a-6616-87b4-772aa283cb38')
            LIMIT 1
            SQL
        );
    }

    public function down(Schema $schema): void
    {
        // No un-patch
    }
}
