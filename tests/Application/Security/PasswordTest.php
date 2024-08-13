<?php

declare(strict_types=1);

namespace BigGive\Identity\Tests\Application\Security;

use BigGive\Identity\Application\Security\AuthenticationException;
use BigGive\Identity\Application\Security\Password;
use BigGive\Identity\Domain\Person;
use BigGive\Identity\Tests\TestCase;

class PasswordTest extends TestCase
{
    public function testHashSuccess(): void
    {
        $hash = Password::hash('somePass123');

        $this->assertIsString($hash);
        $this->assertStringStartsWith('$2y$', $hash);
    }

    public function testVerifySuccess(): void
    {
        $personProphecy = $this->prophesize(Person::class);
        $personProphecy->getPasswordHash()
            // somePass123 blowfish-hashed
            ->willReturn('$2y$10$oR7vPV9UhgcU2.H3zGCS4.fZc1PlWOBQ56/k8cQPTEqzSggcKP9ei')
            ->shouldBeCalledOnce();

        Password::verify('somePass123', $personProphecy->reveal());
        // Implicit no-exceptions-on-verify assertion.
        $this->addToAssertionCount(1);
    }

    public function testVerifyFailure(): void
    {
        $this->expectException(AuthenticationException::class);

        $personProphecy = $this->prophesize(Person::class);
        $personProphecy->getPasswordHash()
            ->willReturn('$2y$10$aUBTMYCP0uNFhNwiGZ0X2.Sx/h7El1zh9Aete3x5U9iqNOTUsy7H')
            ->shouldBeCalledOnce();

        Password::verify('wrongPass456', $personProphecy->reveal());
    }
}
