<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260521160935 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seed 20 animal configurations';
    }

    public function up(Schema $schema): void
    {
        $animals = [
            ['corbeau', 'Corbeau'],
            ['renard', 'Renard'],
            ['loup', 'Loup'],
            ['serpent', 'Serpent'],
            ['tigre', 'Tigre'],
            ['faucon', 'Faucon'],
            ['ours', 'Ours'],
            ['vipere', 'Vipère'],
            ['lynx', 'Lynx'],
            ['puma', 'Puma'],
            ['aigle', 'Aigle'],
            ['requin', 'Requin'],
            ['panthere', 'Panthère'],
            ['scorpion', 'Scorpion'],
            ['coyote', 'Coyote'],
            ['hibou', 'Hibou'],
            ['jaguar', 'Jaguar'],
            ['raton', 'Raton'],
            ['baleine', 'Baleine'],
            ['vautour', 'Vautour'],
        ];

        $now = new \DateTimeImmutable();
        $nowStr = $now->format('Y-m-d H:i:s');

        foreach ($animals as [$alias, $name]) {
            // Tous les animaux utilisent corbeau temporairement
            // TODO: remplacer par les vrais spritesheets quand disponibles
            $responsePath = '/images/sprites/response/wait_corbeau.png';
            $questionPath = '/images/sprites/questions/question_corbeau.png';
            $eliminationPath = '/images/sprites/eliminations/dead_corbeau.png';

            $this->addSql(
                'INSERT INTO animal_configs (alias, animal_name, response_sprite, question_sprite, elimination_sprite, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$alias, $name, $responsePath, $questionPath, $eliminationPath, $nowStr, $nowStr]
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DELETE FROM animal_configs');
    }
}
