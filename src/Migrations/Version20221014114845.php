<?php

declare(strict_types=1);

namespace BigGive\Identity\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * ID-29 – Remove global email address uniqueness; add an email+password non-unique index.
 */
final class Version20221014114845 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fix email_address uniqueness';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_3370D440B08E074E ON Person');
        $this->addSql('CREATE INDEX email_and_password ON Person (email_address, password)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX email_and_password ON person');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_3370D440B08E074E ON person (email_address)');
    }
}
