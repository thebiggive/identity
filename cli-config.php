<?php

declare(strict_types=1);

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Console\ConsoleRunner;
use Psr\Container\ContainerInterface;

/** @var ContainerInterface $container */

$container = require __DIR__ . '/bootstrap.php';

return ConsoleRunner::createHelperSet($container->get(EntityManagerInterface::class));
