<?php

declare(strict_types=1);

use Doctrine\Migrations\Configuration\Migration\ConfigurationArray;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Configuration\EntityManager\ExistingEntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Container\ContainerInterface;

/** @var ContainerInterface $container */
$container = require __DIR__ . '/bootstrap.php';

$entityManager = $container->get(EntityManagerInterface::class);

$config = require __DIR__ . '/migrations.php';

return DependencyFactory::fromEntityManager(
    new ConfigurationArray($config),
    new ExistingEntityManager($entityManager)
);
