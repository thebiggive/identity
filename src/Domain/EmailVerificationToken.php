<?php

declare(strict_types=1);

namespace BigGive\Identity\Domain;

use BigGive\Identity\Application\Security\Password;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Rfc4122\UuidV4;
use Random\Randomizer;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\NotCompromisedPassword;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity()]
class EmailVerificationToken
{
    /**
     * @psalm-suppress UnusedProperty - required for ORM
     */
    #[ORM\Id]
    #[ORM\Column(type: 'integer', unique: true)]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private ?int $id = null;

    #[ORM\Column()]
    public \DateTimeImmutable $created_at;

    #[ORM\Column()]
    public string $email_address;

    #[ORM\Column()]
    public string $random_code;

    private function __construct(string $email_address, string $randomCode, \DateTimeImmutable $created_at)
    {
        $this->email_address = $email_address;
        $this->random_code = $randomCode;
        $this->created_at = $created_at;
    }

    public static function createForEmailAddress(
        string $emailAddress,
        \DateTimeImmutable $at,
        ?Randomizer $randomizer = null
    ): self {
        $randomizer ??= new Randomizer();
        $code = $randomizer->getBytesFromString('0123456789', 6);
        \assert(is_string($code));

        return new self(email_address: $emailAddress, created_at: $at, randomCode: $code);
    }
}
