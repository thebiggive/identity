<?php

declare(strict_types=1);

namespace BigGive\Identity\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20241010165335 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove unwanted person account';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DELETE FROM Person WHERE stripe_customer_id=\'cus_R0MTiGvtZ2FMfa\' LIMIT 1');
    }

    public function down(Schema $schema): void
    {
        throw new \Exception("no undelete");
    }
}
