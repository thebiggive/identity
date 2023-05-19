<?php

declare(strict_types=1);

namespace BigGive\Identity\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Amend a typo account email address
 */
final class Version20230519153412 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Amend a typo account email address';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<SQL
          UPDATE Person SET email_address = REPLACE(email_address, 'nn@', 'n@') WHERE stripe_customer_id = 'cus_NlyfyMQ2hzrTv7' LIMIT 1;
        SQL
        );
    }

    public function down(Schema $schema): void
    {
        // No un-patch.
    }
}
