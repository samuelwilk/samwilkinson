<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251230154915 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add useForIndexCover field to Photo entity for private collection index page control';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE photo ADD COLUMN use_for_index_cover BOOLEAN DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__photo AS SELECT id, collection_id, filename, title, caption, taken_at, width, height, aspect_ratio, exif_data, is_published, uploaded_at, updated_at, sort_order FROM photo');
        $this->addSql('DROP TABLE photo');
        $this->addSql('CREATE TABLE photo (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, collection_id INTEGER DEFAULT NULL, filename VARCHAR(255) DEFAULT NULL, title VARCHAR(255) DEFAULT NULL, caption CLOB DEFAULT NULL, taken_at DATETIME DEFAULT NULL, width INTEGER DEFAULT NULL, height INTEGER DEFAULT NULL, aspect_ratio DOUBLE PRECISION DEFAULT NULL, exif_data CLOB DEFAULT NULL --(DC2Type:json)
        , is_published BOOLEAN NOT NULL, uploaded_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, sort_order INTEGER DEFAULT NULL, CONSTRAINT FK_14B78418514956FD FOREIGN KEY (collection_id) REFERENCES collection (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO photo (id, collection_id, filename, title, caption, taken_at, width, height, aspect_ratio, exif_data, is_published, uploaded_at, updated_at, sort_order) SELECT id, collection_id, filename, title, caption, taken_at, width, height, aspect_ratio, exif_data, is_published, uploaded_at, updated_at, sort_order FROM __temp__photo');
        $this->addSql('DROP TABLE __temp__photo');
        $this->addSql('CREATE INDEX IDX_14B78418514956FD ON photo (collection_id)');
    }
}
