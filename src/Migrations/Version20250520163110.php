<?php

declare(strict_types=1);

namespace BigGive\Identity\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250520163110 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'BG2-2890: Delete unwanted account';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("DELETE from Person where Person.stripe_customer_id = 'cus_MtF9poBxLXwhgQ' LIMIT 1");
    }

    public function down(Schema $schema): void
    {
        // no going back
    }
}
