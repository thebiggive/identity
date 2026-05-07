<?php

declare(strict_types=1);

namespace BigGive\Identity\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260506155955 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Update password for one donor account';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'UPDATE Person SET password = \'$2y$12$3z1tWxM7GtNU75xwTkrGPuq6pSO8bGRVuDpM0y03uZk5dj/HAoKuK\' WHERE Person.id = uuid_to_bin(\'1f13e2bb-f183-6e06-9605-194eaf0aa7e4\') LIMIT 1'
        );
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException();
    }
}
