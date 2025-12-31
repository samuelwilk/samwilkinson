<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251229155127 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE collection (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, cover_photo_id INTEGER DEFAULT NULL, name VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, description CLOB DEFAULT NULL, location_name VARCHAR(255) DEFAULT NULL, country VARCHAR(100) DEFAULT NULL, start_date DATE DEFAULT NULL, end_date DATE DEFAULT NULL, is_restricted BOOLEAN NOT NULL, access_password VARCHAR(255) DEFAULT NULL, allow_downloads BOOLEAN NOT NULL, visual_style CLOB DEFAULT NULL --(DC2Type:json)
        , created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, sort_order INTEGER DEFAULT NULL, CONSTRAINT FK_FC4D6532A69B8AD7 FOREIGN KEY (cover_photo_id) REFERENCES photo (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_FC4D6532989D9B62 ON collection (slug)');
        $this->addSql('CREATE INDEX IDX_FC4D6532A69B8AD7 ON collection (cover_photo_id)');
        $this->addSql('CREATE TABLE photo (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, collection_id INTEGER DEFAULT NULL, filename VARCHAR(255) NOT NULL, title VARCHAR(255) DEFAULT NULL, caption CLOB DEFAULT NULL, taken_at DATETIME DEFAULT NULL, width INTEGER DEFAULT NULL, height INTEGER DEFAULT NULL, aspect_ratio DOUBLE PRECISION DEFAULT NULL, exif_data CLOB DEFAULT NULL --(DC2Type:json)
        , is_published BOOLEAN NOT NULL, uploaded_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, sort_order INTEGER DEFAULT NULL, CONSTRAINT FK_14B78418514956FD FOREIGN KEY (collection_id) REFERENCES collection (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_14B78418514956FD ON photo (collection_id)');
        $this->addSql('CREATE TABLE post (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, title VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, content CLOB NOT NULL, excerpt CLOB DEFAULT NULL, published_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, is_published BOOLEAN NOT NULL, reading_time_minutes INTEGER DEFAULT NULL, tags CLOB DEFAULT NULL --(DC2Type:json)
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5A8A6C8D989D9B62 ON post (slug)');
        $this->addSql('CREATE TABLE project (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, title VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, summary CLOB DEFAULT NULL, content CLOB NOT NULL, tags CLOB DEFAULT NULL --(DC2Type:json)
        , url VARCHAR(255) DEFAULT NULL, github_url VARCHAR(255) DEFAULT NULL, published_at DATETIME DEFAULT NULL, sort_order INTEGER DEFAULT NULL, is_published BOOLEAN NOT NULL, is_featured BOOLEAN NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, thumbnail_image VARCHAR(255) DEFAULT NULL, metrics CLOB DEFAULT NULL --(DC2Type:json)
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_2FB3D0EE989D9B62 ON project (slug)');
        $this->addSql('CREATE TABLE messenger_messages (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, body CLOB NOT NULL, headers CLOB NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , available_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , delivered_at DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)
        )');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0 ON messenger_messages (queue_name)');
        $this->addSql('CREATE INDEX IDX_75EA56E0E3BD61CE ON messenger_messages (available_at)');
        $this->addSql('CREATE INDEX IDX_75EA56E016BA31DB ON messenger_messages (delivered_at)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE collection');
        $this->addSql('DROP TABLE photo');
        $this->addSql('DROP TABLE post');
        $this->addSql('DROP TABLE project');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
