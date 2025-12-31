<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251231073944 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        // Default existing collections to unpublished (false/0)
        $this->addSql('ALTER TABLE collection ADD COLUMN is_published BOOLEAN NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__collection AS SELECT id, cover_photo_id, name, slug, description, location_name, country, start_date, end_date, is_restricted, access_password, allow_downloads, visual_style, created_at, updated_at, sort_order FROM collection');
        $this->addSql('DROP TABLE collection');
        $this->addSql('CREATE TABLE collection (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, cover_photo_id INTEGER DEFAULT NULL, name VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, description CLOB DEFAULT NULL, location_name VARCHAR(255) DEFAULT NULL, country VARCHAR(100) DEFAULT NULL, start_date DATE DEFAULT NULL, end_date DATE DEFAULT NULL, is_restricted BOOLEAN NOT NULL, access_password VARCHAR(255) DEFAULT NULL, allow_downloads BOOLEAN NOT NULL, visual_style CLOB DEFAULT NULL --(DC2Type:json)
        , created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, sort_order INTEGER DEFAULT NULL, CONSTRAINT FK_FC4D6532A69B8AD7 FOREIGN KEY (cover_photo_id) REFERENCES photo (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO collection (id, cover_photo_id, name, slug, description, location_name, country, start_date, end_date, is_restricted, access_password, allow_downloads, visual_style, created_at, updated_at, sort_order) SELECT id, cover_photo_id, name, slug, description, location_name, country, start_date, end_date, is_restricted, access_password, allow_downloads, visual_style, created_at, updated_at, sort_order FROM __temp__collection');
        $this->addSql('DROP TABLE __temp__collection');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_FC4D6532989D9B62 ON collection (slug)');
        $this->addSql('CREATE INDEX IDX_FC4D6532A69B8AD7 ON collection (cover_photo_id)');
    }
}
