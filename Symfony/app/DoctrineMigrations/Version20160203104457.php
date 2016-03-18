<?php

namespace Application\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

class Version20160203104457 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE Architecture (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, UNIQUE INDEX name_idx (name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE Library (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, default_header VARCHAR(255) NOT NULL, folder_name VARCHAR(255) NOT NULL, description VARCHAR(2048) NOT NULL, owner VARCHAR(255) DEFAULT NULL, repo VARCHAR(255) DEFAULT NULL, branch VARCHAR(255) DEFAULT NULL, in_repo_path VARCHAR(255) DEFAULT NULL, notes LONGTEXT DEFAULT NULL, verified TINYINT(1) NOT NULL, active TINYINT(1) NOT NULL, last_commit VARCHAR(255) DEFAULT NULL, url VARCHAR(512) DEFAULT NULL, UNIQUE INDEX header_idx (default_header, folder_name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE LibraryExample (id INT AUTO_INCREMENT NOT NULL, version_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, path VARCHAR(255) NOT NULL, boards VARCHAR(2048) DEFAULT NULL, INDEX IDX_3EE4A5D34BBC2705 (version_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE Partner (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, auth_key VARCHAR(255) NOT NULL, UNIQUE INDEX auth_key_idx (auth_key), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE Preference (id INT AUTO_INCREMENT NOT NULL, library_id INT DEFAULT NULL, partner_id INT DEFAULT NULL, version_id INT DEFAULT NULL, INDEX IDX_1234B383FE2541D7 (library_id), INDEX IDX_1234B3839393F8FE (partner_id), INDEX IDX_1234B3834BBC2705 (version_id), UNIQUE INDEX search_idx (library_id, partner_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE Version (id INT AUTO_INCREMENT NOT NULL, library_id INT DEFAULT NULL, version VARCHAR(255) NOT NULL, description VARCHAR(2048) DEFAULT NULL, notes LONGTEXT DEFAULT NULL, source_url VARCHAR(512) DEFAULT NULL, release_commit VARCHAR(255) DEFAULT NULL, folder_name VARCHAR(255) NOT NULL, INDEX IDX_70A1EA5FFE2541D7 (library_id), UNIQUE INDEX folders_idx (library_id, folder_name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE ArchitectureVersion (version_id INT NOT NULL, architecture_id INT NOT NULL, INDEX IDX_98E4E2F14BBC2705 (version_id), INDEX IDX_98E4E2F173F96878 (architecture_id), PRIMARY KEY(version_id, architecture_id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE LibraryExample ADD CONSTRAINT FK_3EE4A5D34BBC2705 FOREIGN KEY (version_id) REFERENCES Version (id)');
        $this->addSql('ALTER TABLE Preference ADD CONSTRAINT FK_1234B383FE2541D7 FOREIGN KEY (library_id) REFERENCES Library (id)');
        $this->addSql('ALTER TABLE Preference ADD CONSTRAINT FK_1234B3839393F8FE FOREIGN KEY (partner_id) REFERENCES Partner (id)');
        $this->addSql('ALTER TABLE Preference ADD CONSTRAINT FK_1234B3834BBC2705 FOREIGN KEY (version_id) REFERENCES Version (id)');
        $this->addSql('ALTER TABLE Version ADD CONSTRAINT FK_70A1EA5FFE2541D7 FOREIGN KEY (library_id) REFERENCES Library (id)');
        $this->addSql('ALTER TABLE ArchitectureVersion ADD CONSTRAINT FK_98E4E2F14BBC2705 FOREIGN KEY (version_id) REFERENCES Version (id)');
        $this->addSql('ALTER TABLE ArchitectureVersion ADD CONSTRAINT FK_98E4E2F173F96878 FOREIGN KEY (architecture_id) REFERENCES Architecture (id)');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE ArchitectureVersion DROP FOREIGN KEY FK_98E4E2F173F96878');
        $this->addSql('ALTER TABLE Preference DROP FOREIGN KEY FK_1234B383FE2541D7');
        $this->addSql('ALTER TABLE Version DROP FOREIGN KEY FK_70A1EA5FFE2541D7');
        $this->addSql('ALTER TABLE Preference DROP FOREIGN KEY FK_1234B3839393F8FE');
        $this->addSql('ALTER TABLE LibraryExample DROP FOREIGN KEY FK_3EE4A5D34BBC2705');
        $this->addSql('ALTER TABLE Preference DROP FOREIGN KEY FK_1234B3834BBC2705');
        $this->addSql('ALTER TABLE ArchitectureVersion DROP FOREIGN KEY FK_98E4E2F14BBC2705');
        $this->addSql('DROP TABLE Architecture');
        $this->addSql('DROP TABLE Library');
        $this->addSql('DROP TABLE LibraryExample');
        $this->addSql('DROP TABLE Partner');
        $this->addSql('DROP TABLE Preference');
        $this->addSql('DROP TABLE Version');
        $this->addSql('DROP TABLE ArchitectureVersion');
    }
}
