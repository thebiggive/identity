<?php

declare(strict_types=1);

namespace BigGive\Identity\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20230201143918 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add unique index to make sure no two users with passwords set can have the same email address';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<SQL
          CREATE UNIQUE INDEX email_if_password ON Person((CASE WHEN password IS NOT NULL THEN email_address END));
        SQL
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX email_if_password ON Person');
    }
}
