<?php

declare(strict_types=1);

namespace BigGive\Identity\Domain;

use Assert\Assertion;
use Doctrine\ORM\Mapping as ORM;
use Random\Randomizer;

#[ORM\Entity()]
#[ORM\Index(columns: ['email_address'])]
class EmailVerificationToken
{
    /**
     * @psalm-suppress UnusedProperty - required for ORM
     */
    #[ORM\Id]
    #[ORM\Column(type: 'integer', unique: true)]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private ?int $id = null;

    #[ORM\Column(type: 'datetime_immutable')]
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

    /**
     * @throws \Assert\AssertionFailedException if email address is malformed
     */
    public static function createForEmailAddress(
        string $emailAddress,
        \DateTimeImmutable $at,
        ?Randomizer $randomizer = null
    ): self {
        $randomizer ??= new Randomizer();
        $code = $randomizer->getBytesFromString('0123456789', 6);
        \assert(is_string($code));

        Assertion::email($emailAddress);

        return new self(email_address: $emailAddress, created_at: $at, randomCode: $code);
    }

    public static function oldestCreationDateForViewingToken(\DateTimeImmutable $at): \DateTimeImmutable
    {
        return $at->modify('-2 hours');
    }

    public static function oldestCreationDateForSettingPassword(\DateTimeImmutable $at): \DateTimeImmutable
    {
        // (extra two minutes older to allow for user think time on registration page)
        return self::oldestCreationDateForViewingToken($at)->modify('-2 minutes');
    }
}
