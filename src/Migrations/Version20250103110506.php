<?php

declare(strict_types=1);

namespace BigGive\Identity\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create a table for global persisted variables and add one for use in one-off person copy job
 */
final class Version20250103110506 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE KeyValue (`key` varchar(32) not null, value varchar(255))
        SQL
    );
        // At least all the person records updated before the given date (i.e. currently none) have been
        // sent to matchbot.

        $this->addSql(<<<'SQL'
            INSERT INTO KeyValue (`key`, `value`) VALUES 
              ('PersonRecordsSentToMatchBotTo', '1970-01-01T00:00:00+00:00') 
            SQL
            );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE KeyValue');
    }
}
