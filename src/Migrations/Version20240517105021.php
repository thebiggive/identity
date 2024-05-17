<?php

declare(strict_types=1);

namespace BigGive\Identity\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240517105021 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Delete unwanted accounts';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<SQL
                DELETE FROM Person 
                WHERE Person.stripe_customer_id IN ('cus_PyLyjSXfCXTsTr', 'cus_PyZ4LHTGMHq7Qz')
                LIMIT 2;
            SQL
);
    }

    public function down(Schema $schema): void
    {
        throw new \Exception("Irreversible migration");
    }
}
