<?php

namespace BigGive\Identity\Domain;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\JoinColumn;
use Symfony\Component\Uid\Uuid;

/**
 * @ORM\Entity(repositoryClass="BigGive\Identity\Repository\PasswordResetTokenRepository")
 * @ORM\Table(name="PasswordResetToken", indexes={
 *     @ORM\Index(name="secret", columns={"secret"}),
 * })
 */
class PasswordResetToken
{
    use TimestampsTrait;

    /**
     * @ORM\Id
     * @ORM\Column(type="uuid", unique=true)
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class="\Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator")
     */
    private ?Uuid $id = null;

    /**
     * @ORM\Column(type="uuid", unique=true)
     * @ORM\CustomIdGenerator(class="\Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator")
     */
    private readonly Uuid $secret;

    /**
     * @ORM\Column(type="uuid", unique=true)
     * @ManyToOne(targetEntity="Person")
     * @JoinColumn(name="address_id", referencedColumnName="id")
     */
    public readonly Person $person;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private ?\DateTimeImmutable $used = null;

    public function __construct(Person $person)
    {
        $this->person = $person;
        $this->secret = Uuid::v4();
        $this->createdNow();
    }

    public function toBase58Secret(): string
    {
        return $this->secret->toBase58();
    }

    public function setUsed(\DateTimeImmutable $used): void
    {
        $this->used = $used;
    }

    public function isUsed(): bool
    {
        return $this->used !== null;
    }
}
