<?php

namespace App\DataFixtures;

use App\Entity\AnimalConfig;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AnimalConfigFixtures extends Fixture
{
    private const ANIMALS = [
        ['corbeau', 'Corbeau', '/images/sprites/response/wait_corbeau.png', '/images/sprites/questions/question_corbeau.png', '/images/sprites/eliminations/dead_corbeau.png'],
        ['renard', 'Renard', '/images/sprites/response/wait_renard.png', '/images/sprites/questions/question_renard.png', '/images/sprites/eliminations/dead_renard.png'],
        ['loup', 'Loup', '/images/sprites/response/wait_loup.png', '/images/sprites/questions/question_loup.png', '/images/sprites/eliminations/dead_loup.png'],
        ['serpent', 'Serpent', '/images/sprites/response/wait_serpent.png', '/images/sprites/questions/question_serpent.png', '/images/sprites/eliminations/dead_serpent.png'],
        ['tigre', 'Tigre', '/images/sprites/response/wait_corbeau.png', '/images/sprites/questions/question_corbeau.png', '/images/sprites/eliminations/dead_corbeau.png'],
        ['faucon', 'Faucon', '/images/sprites/response/wait_corbeau.png', '/images/sprites/questions/question_corbeau.png', '/images/sprites/eliminations/dead_corbeau.png'],
        ['ours', 'Ours', '/images/sprites/response/wait_corbeau.png', '/images/sprites/questions/question_corbeau.png', '/images/sprites/eliminations/dead_corbeau.png'],
        ['vipere', 'Vipère', '/images/sprites/response/wait_corbeau.png', '/images/sprites/questions/question_corbeau.png', '/images/sprites/eliminations/dead_corbeau.png'],
        ['lynx', 'Lynx', '/images/sprites/response/wait_corbeau.png', '/images/sprites/questions/question_corbeau.png', '/images/sprites/eliminations/dead_corbeau.png'],
        ['puma', 'Puma', '/images/sprites/response/wait_corbeau.png', '/images/sprites/questions/question_corbeau.png', '/images/sprites/eliminations/dead_corbeau.png'],
        ['aigle', 'Aigle', '/images/sprites/response/wait_corbeau.png', '/images/sprites/questions/question_corbeau.png', '/images/sprites/eliminations/dead_corbeau.png'],
        ['requin', 'Requin', '/images/sprites/response/wait_corbeau.png', '/images/sprites/questions/question_corbeau.png', '/images/sprites/eliminations/dead_corbeau.png'],
        ['panthere', 'Panthère', '/images/sprites/response/wait_corbeau.png', '/images/sprites/questions/question_corbeau.png', '/images/sprites/eliminations/dead_corbeau.png'],
        ['scorpion', 'Scorpion', '/images/sprites/response/wait_corbeau.png', '/images/sprites/questions/question_corbeau.png', '/images/sprites/eliminations/dead_corbeau.png'],
        ['coyote', 'Coyote', '/images/sprites/response/wait_corbeau.png', '/images/sprites/questions/question_corbeau.png', '/images/sprites/eliminations/dead_corbeau.png'],
        ['hibou', 'Hibou', '/images/sprites/response/wait_corbeau.png', '/images/sprites/questions/question_corbeau.png', '/images/sprites/eliminations/dead_corbeau.png'],
        ['jaguar', 'Jaguar', '/images/sprites/response/wait_corbeau.png', '/images/sprites/questions/question_corbeau.png', '/images/sprites/eliminations/dead_corbeau.png'],
        ['raton', 'Raton', '/images/sprites/response/wait_corbeau.png', '/images/sprites/questions/question_corbeau.png', '/images/sprites/eliminations/dead_corbeau.png'],
        ['baleine', 'Baleine', '/images/sprites/response/wait_corbeau.png', '/images/sprites/questions/question_corbeau.png', '/images/sprites/eliminations/dead_corbeau.png'],
        ['vautour', 'Vautour', '/images/sprites/response/wait_corbeau.png', '/images/sprites/questions/question_corbeau.png', '/images/sprites/eliminations/dead_corbeau.png'],
    ];

    public function load(ObjectManager $manager): void
    {
        foreach (self::ANIMALS as [$alias, $name, $response, $question, $elimination]) {
            $animal = new AnimalConfig();
            $animal->setAlias($alias);
            $animal->setAnimalName($name);
            $animal->setResponseSprite($response);
            $animal->setQuestionSprite($question);
            $animal->setEliminationSprite($elimination);
            $manager->persist($animal);
        }

        $manager->flush();
    }
}
