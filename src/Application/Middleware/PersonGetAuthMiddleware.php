<?php

declare(strict_types=1);

namespace BigGive\Identity\Application\Middleware;

/**
 * This is used for loading personal data if the donor has set a password (ever) and logged in (recently).
 */
class PersonGetAuthMiddleware extends PersonAuthMiddleware
{
    protected function getCompletePropertyRequirement(): ?bool
    {
        return null;
    }
}
