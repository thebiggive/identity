<?php

declare(strict_types=1);

namespace BigGive\Identity\Application\Middleware;

/**
 * This is used just for setting personal info + password for now. We require the token to have
 * been issued for managing a *not* complete Person record.
 */
class PersonPatchAuthMiddleware extends PersonAuthMiddleware
{
    protected function getCompletePropertyRequirement(): ?bool
    {
        return false;
    }
}
