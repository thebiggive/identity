<?php

declare(strict_types=1);

namespace BigGive\Identity\Application\Middleware;

/**
 * Allows patching a 'complete' person record, i.e. updating details on an account that has a password.
 *
 * At time of writing just used for updating home address.
 */
class CompletePersonPatchAuthMiddleware extends PersonAuthMiddleware
{
    protected function getCompletePropertyRequirement(): ?bool
    {
        return true;
    }
}
