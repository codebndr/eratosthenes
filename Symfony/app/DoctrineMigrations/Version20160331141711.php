<?php

namespace Application\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20160331141711 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE Example DROP FOREIGN KEY FK_A151A203FE2541D7');
        $this->addSql('DROP TABLE Example');
        $this->addSql('DROP TABLE ExternalLibrary');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE Example (id INT AUTO_INCREMENT NOT NULL, library_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, path VARCHAR(255) NOT NULL, boards VARCHAR(2048) DEFAULT NULL, INDEX IDX_A151A203FE2541D7 (library_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE ExternalLibrary (id INT AUTO_INCREMENT NOT NULL, humanName VARCHAR(255) NOT NULL, machineName VARCHAR(255) NOT NULL, description VARCHAR(2048) NOT NULL, owner VARCHAR(255) DEFAULT NULL, repo VARCHAR(255) DEFAULT NULL, verified TINYINT(1) NOT NULL, active TINYINT(1) NOT NULL, lastCommit VARCHAR(255) DEFAULT NULL, url VARCHAR(512) DEFAULT NULL, branch VARCHAR(255) DEFAULT NULL, in_repo_path VARCHAR(255) DEFAULT NULL, notes LONGTEXT DEFAULT NULL, source_url VARCHAR(512) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE Example ADD CONSTRAINT FK_A151A203FE2541D7 FOREIGN KEY (library_id) REFERENCES ExternalLibrary (id)');
    }
}
