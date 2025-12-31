<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251230180404 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add ApiToken entity for bulk photo upload authentication';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE api_token (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, hashed_token VARCHAR(255) NOT NULL, is_active BOOLEAN NOT NULL, last_used_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, expires_at DATETIME DEFAULT NULL)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE api_token');
    }
}
