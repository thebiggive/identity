<?php

declare(strict_types=1);

namespace BigGive\Identity\Tests\Domain;

use BigGive\Identity\Domain\Credentials;
use BigGive\Identity\Tests\TestCase;

class CredentialsTest extends TestCase
{
    public function testBuildAndJsonSerialize(): void
    {
        $credentials = new Credentials();
        $credentials->email_address = 'noel@example.com';
        $credentials->raw_password = 'mySecurePassword123';

        $expected = json_encode([
            'email_address' => 'noel@example.com',
            'raw_password' => 'mySecurePassword123',
        ], JSON_THROW_ON_ERROR);
        $this->assertJsonStringEqualsJsonString(
            $expected,
            json_encode($credentials->jsonSerialize(), JSON_THROW_ON_ERROR),
        );
    }
}
