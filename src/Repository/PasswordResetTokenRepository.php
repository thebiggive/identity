<?php

namespace BigGive\Identity\Repository;

use BigGive\Identity\Domain\PasswordResetToken;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\Uid\Uuid;

class PasswordResetTokenRepository extends EntityRepository
{
    public function persist(PasswordResetToken $token): void
    {
        $this->getEntityManager()->persist($token);
        $this->getEntityManager()->flush();
    }

    public function findBySecret(Uuid $secret): ?PasswordResetToken
    {
        /** @var ?PasswordResetToken $token */
        $token = $this->find($secret);

        return $token;
    }
}
