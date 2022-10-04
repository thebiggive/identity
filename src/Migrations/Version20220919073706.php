<?php

declare(strict_types=1);

namespace BigGive\Identity\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Switch UUIDs' implementation to Symfony's own component.
 */
final class Version20220919073706 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Switch UUIDs' implementation to Symfony's own component";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE PaymentMethod CHANGE id id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE person_id person_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE Person CHANGE id id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE PaymentMethod CHANGE id id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid_binary_ordered_time)\', CHANGE person_id person_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid_binary_ordered_time)\'');
        $this->addSql('ALTER TABLE Person CHANGE id id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid_binary_ordered_time)\'');
    }
}
