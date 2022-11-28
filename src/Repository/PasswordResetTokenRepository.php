<?php

namespace BigGive\Identity\Repository;

use BigGive\Identity\Domain\PasswordResetToken;
use Doctrine\ORM\EntityRepository;

class PasswordResetTokenRepository extends EntityRepository
{
    public function persist(PasswordResetToken $token): void
    {
        $this->getEntityManager()->persist($token);
        $this->getEntityManager()->flush();
    }
}
