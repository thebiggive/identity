<?php

namespace BigGive\Identity\Repository;

use BigGive\Identity\Domain\PasswordResetToken;
use Doctrine\ORM\EntityRepository;

class PasswordResetTokenRepository extends EntityRepository
{
    public function persist(PasswordResetToken $passwordResetToken): void
    {
        // @todo implementation
    }
}