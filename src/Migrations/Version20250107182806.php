<?php

declare(strict_types=1);

namespace BigGive\Identity\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250107182806 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'remove unused key-value pair';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DELETE FROM KeyValue 
            WHERE `key` = 'PersonRecordsSentToMatchBotTo' LIMIT 1; 
            SQL
        );
    }


    public function down(Schema $schema): void
    {
        // no-un-patch
    }
}
