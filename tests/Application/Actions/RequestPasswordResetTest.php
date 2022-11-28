<?php

namespace BigGive\Identity\Tests\Application\Actions;

use BigGive\Identity\Domain\PasswordResetToken;
use BigGive\Identity\Repository\PasswordResetTokenRepository;
use BigGive\Identity\Tests\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

class RequestPasswordResetTest extends TestCase
{
    use ProphecyTrait;

    public function testRequestingPasswordResetStoresToken(): void
    {
        $app = $this->getAppInstance();

        $passwordResetTokenProphecy = $this->prophesize(PasswordResetTokenRepository::class);

        $passwordResetTokenProphecy->persist(Argument::type(PasswordResetToken::class))->shouldBeCalledOnce();
    }
}