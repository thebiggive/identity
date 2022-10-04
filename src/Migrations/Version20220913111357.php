<?php

declare(strict_types=1);

namespace BigGive\Identity\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * ID-18 – Add Person home address fields
 */
final class Version20220913111357 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Person home address fields';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE Person ADD home_address_line_1 VARCHAR(255) DEFAULT NULL, ADD home_postcode VARCHAR(255) DEFAULT NULL, ADD home_country_code VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE Person DROP home_address_line_1, DROP home_postcode, DROP home_country_code');
    }
}
