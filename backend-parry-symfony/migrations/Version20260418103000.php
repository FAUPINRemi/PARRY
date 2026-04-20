<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260418103000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add avatar_base64 and avatar_mime columns to users table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD avatar_base64 TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD avatar_mime VARCHAR(50) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP avatar_base64');
        $this->addSql('ALTER TABLE users DROP avatar_mime');
    }
}
