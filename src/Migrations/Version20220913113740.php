<?php

declare(strict_types=1);

namespace BigGive\Identity\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * ID-18 – Add Person Stripe Customer ID field
 */
final class Version20220913113740 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Person Stripe Customer ID field';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE Person ADD stripe_customer_id VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE Person DROP stripe_customer_id');
    }
}
