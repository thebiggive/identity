<?php

namespace BigGive\Identity\Repository;

use BigGive\Identity\Domain\PasswordResetToken;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\Uid\Uuid;

/**
 * @template-extends EntityRepository<PasswordResetToken>
 */
class PasswordResetTokenRepository extends EntityRepository
{
    public function persist(PasswordResetToken $token): void
    {
        $this->getEntityManager()->persist($token);
        $this->getEntityManager()->flush();
    }

    public function findForUse(Uuid $secret): ?PasswordResetToken
    {
        $query = $this->getEntityManager()->createQuery(
            "SELECT p from \BigGive\Identity\Domain\PasswordResetToken p
            WHERE p.secret = :secret
            AND p.used IS NULL
            AND p.created_at > DATE_SUB(CURRENT_TIMESTAMP(), 1, 'HOUR') 
            "
        );
        $query->setParameter('secret', $secret->toBinary(), Types::BINARY);

        /** @var PasswordResetToken $token */
        $token = $query->getOneOrNullResult();

        return $token;
    }
}
