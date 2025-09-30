<?php

declare(strict_types=1);

namespace BigGive\Identity\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250929180517 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Delete unwanted donor account';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "DELETE FROM PasswordResetToken WHERE PasswordResetToken.person IN (SELECT Id from Person WHERE stripe_customer_id = 'cus_SyVojkUam4cxv5') LIMIT 1"
        );
        $this->addSql("DELETE FROM Person WHERE stripe_customer_id = 'cus_SyVojkUam4cxv5' LIMIT 1");
    }

    public function down(Schema $schema): void
    {
        // no going back
    }
}
