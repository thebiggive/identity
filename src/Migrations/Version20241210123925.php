<?php

declare(strict_types=1);

namespace BigGive\Identity\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20241210123925 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Delete unwanted account';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "DELETE FROM Person WHERE stripe_customer_id = 'cus_RMQWreCSBaHWSB' LIMIT 1"
        );
    }

    public function down(Schema $schema): void
    {
        throw new \Exception('Cant un-delete');
    }
}