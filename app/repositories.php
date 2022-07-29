<?php

declare(strict_types=1);

use BigGive\Identity\Domain\Person;
use BigGive\Identity\Repository\PersonRepository;
use DI\ContainerBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Container\ContainerInterface;

return static function (ContainerBuilder $containerBuilder) {
    $containerBuilder->addDefinitions([
        PersonRepository::class => static function (ContainerInterface $c): PersonRepository {
            return $c->get(EntityManagerInterface::class)->getRepository(Person::class);
        },
    ]);
};
