<?php

declare(strict_types=1);

namespace BigGive\Identity\Application\HttpModels;

/**
 * Request-only payload for setting up new person records.
 *
 * @todo all remaining properties. Maybe combine with ORM model?
 */
class PersonRegistration
{
    /** @var string|null Used only on creates; not persisted. */
    public ?string $creationRecaptchaCode = null;
}
