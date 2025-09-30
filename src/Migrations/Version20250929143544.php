<?php

declare(strict_types=1);

namespace BigGive\Identity\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250929143544 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Delete unwanted donor account';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "DELETE FROM PasswordResetToken WHERE PasswordResetToken.person IN (SELECT Id from Person WHERE stripe_customer_id = 'cus_T7zt2EunTf9Tjc') LIMIT 1"
        );
        $this->addSql("DELETE FROM Person WHERE stripe_customer_id = 'cus_T7zt2EunTf9Tjc' LIMIT 1");
    }

    public function down(Schema $schema): void
    {
        // no going back
    }
}
