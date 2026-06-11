<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260611000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Update renard, loup, serpent to use their own spritesheets';
    }

    public function up(Schema $schema): void
    {
        $animals = [
            'renard' => [
                'response'    => '/images/sprites/response/wait_renard.png',
                'question'    => '/images/sprites/questions/question_renard.png',
                'elimination' => '/images/sprites/eliminations/dead_renard.png',
            ],
            'loup' => [
                'response'    => '/images/sprites/response/wait_loup.png',
                'question'    => '/images/sprites/questions/question_loup.png',
                'elimination' => '/images/sprites/eliminations/dead_loup.png',
            ],
            'serpent' => [
                'response'    => '/images/sprites/response/wait_serpent.png',
                'question'    => '/images/sprites/questions/question_serpent.png',
                'elimination' => '/images/sprites/eliminations/dead_serpent.png',
            ],
        ];

        foreach ($animals as $alias => $sprites) {
            $this->addSql(
                'UPDATE animal_configs SET response_sprite = ?, question_sprite = ?, elimination_sprite = ? WHERE alias = ?',
                [$sprites['response'], $sprites['question'], $sprites['elimination'], $alias]
            );
        }
    }

    public function down(Schema $schema): void
    {
        foreach (['renard', 'loup', 'serpent'] as $alias) {
            $this->addSql(
                'UPDATE animal_configs SET response_sprite = ?, question_sprite = ?, elimination_sprite = ? WHERE alias = ?',
                [
                    '/images/sprites/response/wait_corbeau.png',
                    '/images/sprites/questions/question_corbeau.png',
                    '/images/sprites/eliminations/dead_corbeau.png',
                    $alias,
                ]
            );
        }
    }
}
