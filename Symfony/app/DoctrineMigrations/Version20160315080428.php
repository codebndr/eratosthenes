<?php

namespace Application\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20160315080428 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE Library ADD latest_version_id INT');
        $this->addSql('ALTER TABLE Library ADD CONSTRAINT FK_6E3DA1205F67402F FOREIGN KEY (latest_version_id) REFERENCES Version (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6E3DA1205F67402F ON Library (latest_version_id)');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE Library DROP FOREIGN KEY FK_6E3DA1205F67402F');
        $this->addSql('DROP INDEX UNIQ_6E3DA1205F67402F ON Library');
        $this->addSql('ALTER TABLE Library DROP latest_version_id');
    }
}
