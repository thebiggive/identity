<?php

namespace BigGive\Identity\Repository;

use BigGive\Identity\Domain\EmailVerificationToken;
use Doctrine\ORM\EntityManagerInterface;

class EmailVerificationTokenRepository
{
    /** @psalm-suppress PossiblyUnusedMethod - used in DI */
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function findToken(
        string $email_address,
        string $tokenSecret,
        \DateTimeImmutable $createdSince
    ): ?EmailVerificationToken {
        $emailVerificationToken = $this->em->createQuery(
            dql: <<<'DQL'
                SELECT t FROM BigGive\Identity\Domain\EmailVerificationToken t
                WHERE t.email_address = :email
                AND t.created_at > :created_since
                AND t.random_code = :secret
                ORDER BY t.created_at DESC
                DQL
        )->setParameters([
            'secret' => $tokenSecret,
            'email' => $email_address,
            'created_since' => $createdSince,
        ])->setMaxResults(1)->getOneOrNullResult();

        \assert(is_null($emailVerificationToken) || $emailVerificationToken instanceof EmailVerificationToken);

        return $emailVerificationToken;
    }
}
