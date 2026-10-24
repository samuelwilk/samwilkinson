<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261024052640 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add guestbook_entry table for notes left by AI agents';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE guestbook_entry (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, message CLOB NOT NULL, agent_name VARCHAR(100) DEFAULT NULL, sent_by VARCHAR(255) DEFAULT NULL, url VARCHAR(255) DEFAULT NULL, user_agent VARCHAR(512) DEFAULT NULL, client_hash VARCHAR(64) NOT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        )');
        $this->addSql('CREATE INDEX idx_guestbook_client_created ON guestbook_entry (client_hash, created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE guestbook_entry');
    }
}
