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
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\RoutableMessageBus;

return static function (ContainerBuilder $containerBuilder) {
    $containerBuilder->addDefinitions([
        PersonRepository::class => static function (ContainerInterface $c): PersonRepository {
            /** @var PersonRepository $repo */
            $repo = $c->get(EntityManagerInterface::class)->getRepository(Person::class);

            $mailerClient = $c->get(Mailer::class);
            \assert($mailerClient instanceof Mailer);
            $bus = $c->get(RoutableMessageBus::class);
            \assert($bus instanceof RoutableMessageBus);
            $logger = $c->get(LoggerInterface::class);
            \assert($logger instanceof LoggerInterface);

            $repo->setBus($bus);
            $repo->setLogger($logger);
            $repo->setMailerClient($mailerClient);

            return $repo;
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
