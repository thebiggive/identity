<?php

declare(strict_types=1);

namespace BigGive\Identity\Tests\Application\Auth;

use BigGive\Identity\Application\Auth\Token;
use BigGive\Identity\Tests\TestCase;
use BigGive\Identity\Tests\TestLogger;
use Firebase\JWT\JWT;
use Psr\Log\AbstractLogger;
use Psr\Log\NullLogger;

class TokenTest extends TestCase
{
    public function tearDown(): void
    {
        JWT::$timestamp = null;
        parent::tearDown();
    }

    public function testCreateReturnsValidLookingToken(): void
    {
        $token = Token::create(new \DateTimeImmutable(), 'somePersonId', true, 'cus_aaaaaaaaaaaa11');

        $this->assertMatchesRegularExpression('/^[^.]+\.[^.]+\.[^.]+$/', $token);
    }

    public function testCheckPassesWhenAllValid(): void
    {
        $token = Token::create(new \DateTimeImmutable(), 'somePersonId', true, 'cus_aaaaaaaaaaaa11');

        $this->assertTrue(Token::check('somePersonId', true, $token, new NullLogger()));
    }

    public function testCheckFailsWhenWrongPersonId(): void
    {
        $token = Token::create(new \DateTimeImmutable(), 'somePersonId', true, 'cus_aaaaaaaaaaaa11');

        $this->assertFalse(Token::check('someOtherPersonId', true, $token, new NullLogger()));
    }

    public function testCheckFailsWhenSignatureGarbled(): void
    {
        $token = Token::create(new \DateTimeImmutable(), 'somePersonId', true, 'cus_aaaaaaaaaaaa11');

        $this->assertFalse(Token::check('somePersonId', true, $token . 'X', new NullLogger()));
    }

    public function testCheckFailsWithWrongCompletenessFlag(): void
    {
        $token = Token::create(new \DateTimeImmutable(), 'somePersonId', false, 'cus_aaaaaaaaaaaa11');

        $this->assertFalse(Token::check('somePersonId', true, $token, new NullLogger()));
    }

    public function testTokenValidFor7Hours59(): void
    {
        $log = new TestLogger();
        $start = new \DateTimeImmutable('2025-01-01T00:00:00');
        $checkTime = new \DateTimeImmutable('2025-01-01T07:59:59');

        $token = Token::create($start, 'somePersonId', true, 'cus_aaaaaaaaaaaa11');

        JWT::$timestamp = ($checkTime)->getTimestamp();

        $this->assertTrue(Token::check('somePersonId', true, $token, $log));
        $this->assertEmpty($log->messages);
    }

    public function testTokenExpiresInEightHours(): void
    {
        $log = new TestLogger();
        $start = new \DateTimeImmutable('2025-01-01T00:00:00');
        $checkTime = new \DateTimeImmutable('2025-01-01T08:00:00');

        $token = Token::create($start, 'somePersonId', true, 'cus_aaaaaaaaaaaa11');

        JWT::$timestamp = ($checkTime)->getTimestamp();

        $this->assertFalse(Token::check('somePersonId', true, $token, $log));
        $this->assertSame(
            [
                [
                'level' => 'warning',
                'message' =>
                    'JWT error: decoding for person ID somePersonId: Firebase\JWT\ExpiredException - Expired token',
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

        $token = Token::create($start, 'somePersonId', false, 'cus_aaaaaaaaaaaa11');

        JWT::$timestamp = ($checkTime)->getTimestamp();

        $this->assertFalse(Token::check('somePersonId', true, $token, new NullLogger()));
    }
}
