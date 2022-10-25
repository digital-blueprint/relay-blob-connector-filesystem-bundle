<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobConnectorLocalBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create table blob_connector_local
 */
final class Version20221024082730 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create table blob_connector_local';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE blob_connector_local (identifier INT AUTO_INCREMENT NOT NULL, valid_until DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', link VARCHAR(255) NOT NULL, file_data_identifier VARCHAR(255) NOT NULL, PRIMARY KEY(identifier)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE blob_connector_local');
    }
}
