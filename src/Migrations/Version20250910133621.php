<?php

declare(strict_types=1);

namespace BigGive\Identity\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250910133621 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Delete unwanted donor account';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("DELETE FROM Person WHERE id = UUID_TO_BIN('1f08a6f2-af32-65a8-b5c1-ab36e8090e2a') LIMIT 1");
    }

    public function down(Schema $schema): void
    {
        // no going back
    }
}
