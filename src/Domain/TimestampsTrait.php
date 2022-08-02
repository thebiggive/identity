<?php

declare(strict_types=1);

namespace BigGive\Identity\Domain;

use DateTime;
use Doctrine\ORM\Mapping as ORM;

/**
 * Trait to define timestamp fields and set them when appropriate. For this to work the models *must* be
 * annotated with `@ORM\HasLifecycleCallbacks` at class level.
 */
trait TimestampsTrait
{
    /**
     * @ORM\Column(type="datetime")
     * @var DateTime
     */
    public DateTime $created_at;

    /**
     * @ORM\Column(type="datetime")
     * @var DateTime
     */
    public DateTime $updated_at;

    /**
     * @ORM\PrePersist Set created + updated timestamps
     */
    public function createdNow(): void
    {
        $this->created_at = new \DateTime('now');
        $this->updated_at = new \DateTime('now');
    }

    /**
     * @ORM\PreUpdate Set updated timestamp
     */
    public function updatedNow(): void
    {
        $this->updated_at = new \DateTime('now');
    }
}
