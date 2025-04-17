<?php

namespace BigGive\Identity\Tests\Domain;

use BigGive\Identity\Domain\EmailVerificationToken;
use BigGive\Identity\Domain\Person;
use BigGive\Identity\Tests\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

class EmailVerificationTokenTest extends TestCase
{
    public function testItGeneratesEmailVerificationCode(): void
    {
        $randomizer = new Randomizer(new Mt19937(1));

        $time = new \DateTimeImmutable('2025-01-01 00:00:00');

        $token = EmailVerificationToken::createForEmailAddress('email@example.com', $time, $randomizer);

        $this->assertSame($time, $token->created_at);
        $this->assertEquals('541077', $token->random_code);
        $this->assertEquals('email@example.com', $token->email_address);
    }

    public function testATokenGeneratedAtNoonCanBeViewedUntilTwo(): void
    {
        $this->assertEquals(
            new \DateTimeImmutable('2025-01-01 12:00:00'),
            EmailVerificationToken::oldestCreationDateForViewingToken(
                at: new \DateTimeImmutable('2025-01-01 14:00:00')
            )
        );
    }

    public function testATokenGeneratedAtNoonCanBeUSedUntilTwoOhTwo(): void
    {
        $this->assertEquals(
            new \DateTimeImmutable('2025-01-01 12:00:00'),
            EmailVerificationToken::oldestCreationDateForSettingPassword(
                at: new \DateTimeImmutable('2025-01-01 14:02:00')
            )
        );
    }
}
