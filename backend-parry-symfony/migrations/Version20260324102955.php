<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260324102955 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seed question categories for AI question generation';
    }

    public function up(Schema $schema): void
    {
        $categories = [
            'Voyage et aventure', 'Nourriture et cuisine', 'Musique et sons',
            'Animaux et nature', 'Enfance et souvenirs', 'Sport et compétition',
            'Films et séries', 'Technologie et gadgets', 'Rêves et imagination',
            'Peurs et phobies', 'Amitié et relations', 'Argent et achats',
            'Humour et blagues', 'Couleurs et esthétique', 'Métiers et ambitions',
            'Jeux et divertissements', 'Saisons et météo', 'Habitudes et rituels',
            'Superpouvoir et fiction', 'Histoire et culture',
        ];

        foreach ($categories as $name) {
            $this->addSql(
                "INSERT INTO question_category (name, is_active) VALUES (:name, true) ON CONFLICT (name) DO NOTHING",
                ['name' => $name]
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM question_category WHERE name IN (
            'Voyage et aventure','Nourriture et cuisine','Musique et sons',
            'Animaux et nature','Enfance et souvenirs','Sport et compétition',
            'Films et séries','Technologie et gadgets','Rêves et imagination',
            'Peurs et phobies','Amitié et relations','Argent et achats',
            'Humour et blagues','Couleurs et esthétique','Métiers et ambitions',
            'Jeux et divertissements','Saisons et météo','Habitudes et rituels',
            'Superpouvoir et fiction','Histoire et culture'
        )");
    }
}
