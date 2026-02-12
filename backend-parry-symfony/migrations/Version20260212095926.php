<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260212095926 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE rounds ADD eliminated_player_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE rounds ADD CONSTRAINT FK_3A7FD554B96971E1 FOREIGN KEY (eliminated_player_id) REFERENCES users (id) NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_3A7FD554B96971E1 ON rounds (eliminated_player_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE rounds DROP CONSTRAINT FK_3A7FD554B96971E1');
        $this->addSql('DROP INDEX IDX_3A7FD554B96971E1');
        $this->addSql('ALTER TABLE rounds DROP eliminated_player_id');
    }
}
