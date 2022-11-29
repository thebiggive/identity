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

    public function findForUse(Uuid $secret): ?PasswordResetToken
    {
        $query = $this->_em->createQuery(
            'SELECT p from \BigGive\Identity\Domain\PasswordResetToken u 
            WHERE p.secret = :secret
            AND p.used IS NULL
            AND p.created > DATE_SUB(NOW(), 1, "HOUR") 
            '
        );
        $query->setParameter('secret', $secret);

        /** @var PasswordResetToken $token */
        $token = $query->getOneOrNullResult();

        return $token;
    }
}
