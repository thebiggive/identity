<?php

declare(strict_types=1);

namespace BigGive\Identity\Tests\Application\Auth;

use BigGive\Identity\Application\Auth\Token;
use BigGive\Identity\Application\Auth\TokenService;
use BigGive\Identity\Tests\TestCase;
use BigGive\Identity\Tests\TestLogger;
use Firebase\JWT\JWT;
use Psr\Log\AbstractLogger;
use Psr\Log\NullLogger;

class TokenTest extends TestCase
{
    private TokenService $tokenService;

    public function setUp(): void
    {
        $this->tokenService = new TokenService(['some_secret', 'old_secret']);
    }

    public function tearDown(): void
    {
        JWT::$timestamp = null;
        parent::tearDown();
    }

    public function testCreateReturnsValidLookingToken(): void
    {
        $token = $this->tokenService->create(new \DateTimeImmutable(), 'somePersonId', true, 'cus_aaaaaaaaaaaa11');

        $this->assertMatchesRegularExpression('/^[^.]+\.[^.]+\.[^.]+$/', $token);
    }

    public function testCheckPassesWhenAllValid(): void
    {
        $token = $this->tokenService->create(new \DateTimeImmutable(), 'somePersonId', true, 'cus_aaaaaaaaaaaa11');

        $this->assertTrue($this->tokenService->check('somePersonId', true, $token, new NullLogger()));
    }

    public function testCheckPassesWhenValidAgainstAnOlderSecret(): void
    {
        $oldTokenService = new TokenService(['old_secret']);
        $token = $oldTokenService->create(new \DateTimeImmutable(), 'somePersonId', true, 'cus_aaaaaaaaaaaa11');

        $this->assertTrue($this->tokenService->check('somePersonId', true, $token, new NullLogger()));
    }

    public function testCheckFailsWhenWrongPersonId(): void
    {
        $token = $this->tokenService->create(new \DateTimeImmutable(), 'somePersonId', true, 'cus_aaaaaaaaaaaa11');

        $this->assertFalse($this->tokenService->check('someOtherPersonId', true, $token, new NullLogger()));
    }

    public function testCheckFailsWhenSignatureGarbled(): void
    {
        $token = $this->tokenService->create(new \DateTimeImmutable(), 'somePersonId', true, 'cus_aaaaaaaaaaaa11');

        $this->assertFalse($this->tokenService->check('somePersonId', true, $token . 'X', new NullLogger()));
    }

    public function testCheckFailsWithWrongCompletenessFlag(): void
    {
        $token = $this->tokenService->create(new \DateTimeImmutable(), 'somePersonId', false, 'cus_aaaaaaaaaaaa11');

        $this->assertFalse($this->tokenService->check('somePersonId', true, $token, new NullLogger()));
    }

    public function testTokenValidFor7Hours59(): void
    {
        $log = new TestLogger();
        $start = new \DateTimeImmutable('2025-01-01T00:00:00');
        $checkTime = new \DateTimeImmutable('2025-01-01T07:59:59');

        $token = $this->tokenService->create($start, 'somePersonId', true, 'cus_aaaaaaaaaaaa11');

        JWT::$timestamp = ($checkTime)->getTimestamp();

        $this->assertTrue($this->tokenService->check('somePersonId', true, $token, $log));
        $this->assertEmpty($log->messages);
    }

    public function testTokenExpiresInEightHours(): void
    {
        $log = new TestLogger();
        $start = new \DateTimeImmutable('2025-01-01T00:00:00');
        $checkTime = new \DateTimeImmutable('2025-01-01T08:00:00');

        $token = $this->tokenService->create($start, 'somePersonId', true, 'cus_aaaaaaaaaaaa11');

        JWT::$timestamp = ($checkTime)->getTimestamp();

        $this->assertFalse($this->tokenService->check('somePersonId', true, $token, $log));
        $this->assertSame(
            [
                [
                'level' => 'warning',
                'message' =>
                    'JWT error: decoding for person ID somePersonId: Firebase\JWT\SignatureInvalidException ' .
                    '- Signature verification failed',
                'context' => []
                ]
            ],
            $log->messages
        );
    }

    public function testTokenForGuestUserExpiresInOneHour(): void
    {
        $start = new \DateTimeImmutable('2025-01-01T00:00:00');
        $checkTime = new \DateTimeImmutable('2025-01-01T01:00:00');

        $token = $this->tokenService->create($start, 'somePersonId', false, 'cus_aaaaaaaaaaaa11');

        JWT::$timestamp = ($checkTime)->getTimestamp();

        $this->assertFalse($this->tokenService->check('somePersonId', true, $token, new NullLogger()));
    }
}
