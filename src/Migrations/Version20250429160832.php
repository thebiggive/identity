<?php

declare(strict_types=1);

namespace BigGive\Identity\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Removes long numbers from names - we think these were entered by mistake and could be sensitive
 */
final class Version20250429160832 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove numbers from names';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
        UPDATE Person set first_name = REGEXP_REPLACE(first_name, '[0-9]{6}', '')
        WHERE Person.first_name rlike '[0-9]{6}'
        LIMIT 20;
        SQL
);
    }

    public function down(Schema $schema): void
    {
        // no going back
    }
}
