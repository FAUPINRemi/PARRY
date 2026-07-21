<?php

namespace App\DataFixtures;

use App\Entity\AnimalConfig;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AnimalConfigFixtures extends Fixture
{
    // Un jeu de sprites par animal "source" ; plusieurs alias peuvent partager
    // la même source (voir ANIMALS) pour éviter de dessiner un visuel par animal.
    private const SPRITE_SOURCES = [
        'corbeau'  => ['/images/sprites/response/wait_corbeau.png', '/images/sprites/questions/question_corbeau.png', '/images/sprites/eliminations/dead_corbeau.png'],
        'renard'   => ['/images/sprites/response/wait_renard.png', '/images/sprites/questions/question_renard.png', '/images/sprites/eliminations/dead_renard.png'],
        'loup'     => ['/images/sprites/response/wait_loup.png', '/images/sprites/questions/question_loup.png', '/images/sprites/eliminations/dead_loup.png'],
        'serpent'  => ['/images/sprites/response/wait_serpent.png', '/images/sprites/questions/question_serpent.png', '/images/sprites/eliminations/dead_serpent.png'],
        'tigre'    => ['/images/sprites/response/wait_tigre.png', '/images/sprites/questions/question_tigre.png', '/images/sprites/eliminations/dead_tigre.png'],
        'faucon'   => ['/images/sprites/response/wait_faucon.png', '/images/sprites/questions/question_faucon.png', '/images/sprites/eliminations/dead_faucon.png'],
        'ours'     => ['/images/sprites/response/wait_ours.png', '/images/sprites/questions/question_ours.png', '/images/sprites/eliminations/dead_ours.png'],
        'requin'   => ['/images/sprites/response/wait_requin.png', '/images/sprites/questions/question_requin.png', '/images/sprites/eliminations/dead_requin.png'],
        'scorpion' => ['/images/sprites/response/wait_scorpion.png', '/images/sprites/questions/question_scorpion.png', '/images/sprites/eliminations/dead_scorpion.png'],
        'hibou'    => ['/images/sprites/response/wait_hibou.png', '/images/sprites/questions/question_hibou.png', '/images/sprites/eliminations/dead_hibou.png'],
        'baleine'  => ['/images/sprites/response/wait_baleine.png', '/images/sprites/questions/question_baleine.png', '/images/sprites/eliminations/dead_baleine.png'],
    ];

    // [alias, nom affiché, source des sprites]. Raton et Vautour n'ont pas
    // encore de visuel dédié ni de source à partager : ils restent sur
    // 'corbeau' en attendant.
    private const ANIMALS = [
        ['corbeau', 'Corbeau', 'corbeau'],
        ['renard', 'Renard', 'renard'],
        ['loup', 'Loup', 'loup'],
        ['serpent', 'Serpent', 'serpent'],
        ['vipere', 'Vipère', 'serpent'],
        ['coyote', 'Coyote', 'renard'],
        ['tigre', 'Tigre', 'tigre'],
        ['faucon', 'Faucon', 'faucon'],
        ['lynx', 'Lynx', 'tigre'],
        ['puma', 'Puma', 'tigre'],
        ['panthere', 'Panthère', 'tigre'],
        ['jaguar', 'Jaguar', 'tigre'],
        ['aigle', 'Aigle', 'faucon'],
        ['ours', 'Ours', 'ours'],
        ['requin', 'Requin', 'requin'],
        ['scorpion', 'Scorpion', 'scorpion'],
        ['hibou', 'Hibou', 'hibou'],
        ['baleine', 'Baleine', 'baleine'],
        ['raton', 'Raton', 'corbeau'],
        ['vautour', 'Vautour', 'corbeau'],
    ];

    public function load(ObjectManager $manager): void
    {
        foreach (self::ANIMALS as [$alias, $name, $spriteSource]) {
            [$response, $question, $elimination] = self::SPRITE_SOURCES[$spriteSource];

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
