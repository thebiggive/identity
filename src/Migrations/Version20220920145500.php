<?php

declare(strict_types=1);

namespace BigGive\Identity\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * ID-21 – Drop PaymentMethod.
 */
final class Version20220920145500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop PaymentMethod';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE PaymentMethod DROP FOREIGN KEY FK_37FAAE8D217BBB47');
        $this->addSql('DROP TABLE PaymentMethod');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE PaymentMethod (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', person_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', psp VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, token VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, billing_first_address_line VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, billing_postcode VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, billing_country_code VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_37FAAE8D217BBB47 (person_id), UNIQUE INDEX UNIQ_37FAAE8D5F37A13B (token), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE PaymentMethod ADD CONSTRAINT FK_37FAAE8D217BBB47 FOREIGN KEY (person_id) REFERENCES Person (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
    }
}
