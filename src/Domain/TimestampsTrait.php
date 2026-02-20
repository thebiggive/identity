<?php

declare(strict_types=1);

namespace BigGive\Identity\Domain;

use Doctrine\ORM\Mapping as ORM;

/**
 * Trait to define timestamp fields and set them when appropriate. For this to work the models *must*
 * have the `#[ORM\HasLifecycleCallbacks]` attribute at class level.
 */
trait TimestampsTrait
{
    #[ORM\Column(type: 'datetime_immutable')]
    public \DateTimeImmutable $created_at;

    #[ORM\Column(type: 'datetime_immutable')]
    public \DateTimeImmutable $updated_at;

    #[ORM\PrePersist]
    final public function createdNow(): void
    {
        $now = new \DateTimeImmutable('now');
        $this->created_at = $now;
        $this->updated_at = $now;
    }

    #[ORM\PreUpdate]
    public function updatedNow(): void
    {
        $this->updated_at = new \DateTimeImmutable('now');
    }
}
