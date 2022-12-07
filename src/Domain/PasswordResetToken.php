<?php

namespace BigGive\Identity\Domain;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\JoinColumn;
use Exception;
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
     * @ManyToOne(targetEntity="Person")
     * @JoinColumn(name="person", referencedColumnName="id")
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
        $this->created_at = new \DateTime();
        $this->updated_at = new \DateTime();
    }

    public function toBase58Secret(): string
    {
        return $this->secret->toBase58();
    }

    /**
     * @throws Exception if the token was created more than an hour before usage time, or was already used.
     * Neither should happen in production as both conditions are checked by PasswordResetTokenRepository::findForUse
     * but we check here also for belt-and-braces.
     */
    public function consume(\DateTimeImmutable $usageTime): void
    {
        // allow an entire minute extra in case we were really slow getting to this point after fetching from DB,
        // or for clock-skew
        $sixtyOneMinutes = new \DateInterval("PT61M");

        if ($this->created_at < $usageTime->sub($sixtyOneMinutes)) {
            throw new Exception('Token expired');
        }

        if ($this->isUsed()) {
            throw new Exception('Token already used');
        }

        $this->used = $usageTime;
    }

    public function isUsed(): bool
    {
        return $this->used !== null;
    }
}
