<?php

declare(strict_types=1);

namespace BigGive\Identity\Application\Middleware;

/**
 * Allows writing a 'complete' person record, i.e. deleting or updating details on an account that has a password.
 *
 */
class CompletePersonWriteAuthMiddleware extends PersonAuthMiddleware
{
    protected function getCompletePropertyRequirement(): ?bool
    {
        return true;
    }
}
