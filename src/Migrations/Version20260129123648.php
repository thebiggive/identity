<?php

declare(strict_types=1);

namespace BigGive\Identity\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260129123648 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Person.is_organisation field';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE Person ADD is_organisation TINYINT(1) NULL');
        $this->addSql('UPDATE Person SET is_organisation = 0 WHERE 1');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE Person DROP is_organisation');
    }
}
