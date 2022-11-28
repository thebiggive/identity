<?php

namespace BigGive\Identity\Domain;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * @ORM\Entity(repositoryClass="BigGive\Identity\Repository\PasswordResetTokenRepository")
 * @ORM\Table(name="PasswordResetToken", indexes={
 *     @ORM\Index(name="secret", columns={"secret"}),
 * })
 */
class PasswordResetToken
{
    /**
     * @ORM\Id
     * @ORM\Column(type="uuid", unique=true)
     * @ORM\CustomIdGenerator(class="\Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator")
     */
    private Uuid $secret;

    /**
     * @ORM\Column(type="uuid", unique=true)
     */
    public readonly Uuid $personId;

    public function __construct(Uuid $personId)
    {
        $this->personId = $personId;
        $this->secret = Uuid::v4();
    }

    public function toBase58Secret(): string
    {
        return $this->secret->toBase58();
    }
}
