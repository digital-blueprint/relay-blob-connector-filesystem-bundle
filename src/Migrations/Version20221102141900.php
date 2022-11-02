<?php

declare(strict_types=1);

namespace Dbp\Relay\BlobConnectorFilesystemBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Set identifier to unique
 */
final class Version20221102141900 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Set identifier to unique';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE blob_connector_filesystem ADD UNIQUE (identifier); ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE blob_connector_filesystem DROP INDEX identifier; ');
    }
}
