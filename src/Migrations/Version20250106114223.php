<?php

declare(strict_types=1);

namespace BigGive\Identity\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * After syncing all person records from staging to matchbot we found we missed syncing the uuid field
 * for existing records. So we need to go back to the start and do them again.
 *
 * This won't affect production as we haven't started there yet anyway.
 */
final class Version20250106114223 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Restart syncing person records to matchbot';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            UPDATE KeyValue 
            SET `value` = '1970-01-01T00:00:00+00:00' 
            WHERE `key` = 'PersonRecordsSentToMatchBotTo' LIMIT 1; 
            SQL
        );

    }

    public function down(Schema $schema): void
    {
        // No un-patch
    }
}
