<?php

declare(strict_types=1);

namespace BigGive\Identity\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * ID-2 Add key Person + PaymentMethod fields; switch to binary UUIDs as primary keys.
 */
final class Version20220708163803 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Person + PaymentMethod fields; switch to binary UUIDs as primary keys';
    }

    public function up(Schema $schema): void
    {
        // Had to manually drop FK before the `id` change then re-add at the end, otherwise one
        // of the changes complains about the type incompatibility.
        $this->addSql('ALTER TABLE PaymentMethod DROP FOREIGN KEY FK_37FAAE8D217BBB47');

        $this->addSql('ALTER TABLE PaymentMethod ADD psp VARCHAR(255) NOT NULL, ADD token VARCHAR(255) NOT NULL, ADD billing_first_address_line VARCHAR(255) DEFAULT NULL, ADD billing_postcode VARCHAR(255) DEFAULT NULL, ADD billing_country_code VARCHAR(255) DEFAULT NULL, CHANGE id id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid_binary_ordered_time)\', CHANGE person_id person_id BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid_binary_ordered_time)\'');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_37FAAE8D5F37A13B ON PaymentMethod (token)');
        $this->addSql('ALTER TABLE Person ADD first_name VARCHAR(255) NOT NULL, ADD last_name VARCHAR(255) NOT NULL, ADD emailAddress VARCHAR(255) NOT NULL, CHANGE id id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid_binary_ordered_time)\'');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_3370D440465A626E ON Person (emailAddress)');

        // Manually added step, as above.
        $this->addSql('ALTER TABLE PaymentMethod ADD CONSTRAINT FK_37FAAE8D217BBB47 FOREIGN KEY (person_id) REFERENCES Person (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_37FAAE8D5F37A13B ON PaymentMethod');
        $this->addSql('ALTER TABLE PaymentMethod DROP psp, DROP token, DROP billing_first_address_line, DROP billing_postcode, DROP billing_country_code, CHANGE id id INT UNSIGNED AUTO_INCREMENT NOT NULL, CHANGE person_id person_id INT UNSIGNED DEFAULT NULL');
        $this->addSql('DROP INDEX UNIQ_3370D440465A626E ON Person');
        $this->addSql('ALTER TABLE Person DROP first_name, DROP last_name, DROP emailAddress, CHANGE id id INT UNSIGNED AUTO_INCREMENT NOT NULL');
    }
}
