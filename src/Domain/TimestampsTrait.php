<?php

declare(strict_types=1);

namespace Tbg\Identity\Domain;

use DateTime;

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
    protected DateTime $createdAt;

    /**
     * @ORM\Column(type="datetime")
     * @var DateTime
     */
    protected DateTime $updatedAt;

    /**
     * @ORM\PrePersist Set created + updated timestamps
     */
    public function createdNow(): void
    {
        $this->createdAt = new \DateTime('now');
        $this->updatedAt = new \DateTime('now');
    }

    /**
     * @ORM\PreUpdate Set updated timestamp
     */
    public function updatedNow(): void
    {
        $this->updatedAt = new \DateTime('now');
    }

    /**
     * @return DateTime
     */
    public function getCreatedDate(): DateTime
    {
        return $this->createdAt;
    }

    /**
     * @return DateTime
     */
    public function getUpdatedDate(): DateTime
    {
        return $this->updatedAt;
    }
}
