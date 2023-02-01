<?php

declare(strict_types=1);

use BigGive\Identity\Client\Mailer;
use BigGive\Identity\Domain\PasswordResetToken;
use BigGive\Identity\Domain\Person;
use BigGive\Identity\Repository\PasswordResetTokenRepository;
use BigGive\Identity\Repository\PersonRepository;
use DI\ContainerBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Container\ContainerInterface;

return static function (ContainerBuilder $containerBuilder) {
    $containerBuilder->addDefinitions([
        PersonRepository::class => static function (ContainerInterface $c): PersonRepository {
            $entityManager = $c->get(EntityManagerInterface::class);
            $entityRepository = $entityManager->getRepository(Person::class);

            $personRepository = new PersonRepository($entityManager, $entityRepository);
            /** @var Mailer $mailerClient */
            $mailerClient = $c->get(Mailer::class);
            $personRepository->setMailerClient($mailerClient);

            return $personRepository;
        },
        PasswordResetTokenRepository::class => static function (ContainerInterface $c): PasswordResetTokenRepository {
            /** @var EntityManagerInterface $entityManager */
            $entityManager = $c->get(EntityManagerInterface::class);

            /** @var PasswordResetTokenRepository $repository */
            $repository = $entityManager->getRepository(PasswordResetToken::class);
            return $repository;
        },
    ]);
};
