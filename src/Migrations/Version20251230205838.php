<?php

declare(strict_types=1);

namespace BigGive\Identity\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251230205838 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Delete unwanted donor account';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<SQL
            DELETE FROM PasswordResetToken
            WHERE PasswordResetToken.person = UUID_TO_BIN('1ede2af3-f901-6c88-b17d-d9e14716d0e9')
            LIMIT 1
            SQL
        );

        $this->addSql(<<<SQL
            DELETE FROM Person
            WHERE Person.id = UUID_TO_BIN('1ede2af3-f901-6c88-b17d-d9e14716d0e9')
            LIMIT 1
            SQL
        );
    }

    public function down(Schema $schema): void
    {
        // no going back
    }
}
