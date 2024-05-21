<?php

declare(strict_types=1);

namespace BigGive\Identity\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240521150231 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'BG2-2633: Delete unwanted accounts';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<SQL
                DELETE FROM Person 
                WHERE Person.stripe_customer_id IN ('cus_Q6z8AlZeDZN7XE', 'cus_Q97wauMHN5VSmi')
                LIMIT 2;
        SQL
);
    }

    public function down(Schema $schema): void
    {
        throw new \Exception("No going back");
    }
}
