<?php

declare(strict_types=1);

namespace BigGive\Identity\Domain;

use Doctrine\ORM\Mapping as ORM;

/**
 * Trait to define timestamp fields and set them when appropriate. For this to work the models *must* be
 * annotated with `@ORM\HasLifecycleCallbacks` at class level.
 */
trait TimestampsTrait
{
    /**
     * @var \DateTimeInterface
     */
    #[ORM\Column(type: 'datetime')]
    public \DateTimeInterface $created_at;

    /**
     * @var \DateTimeInterface
     */
    #[ORM\Column(type: 'datetime')]
    public \DateTimeInterface $updated_at;

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
        $this->updated_at = new \DateTime('now');
    }
}
