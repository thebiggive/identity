<?php

declare(strict_types=1);

namespace BigGive\Identity\Tests\Application\Auth;

use BigGive\Identity\Application\Auth\Token;
use BigGive\Identity\Tests\TestCase;
use Firebase\JWT\JWT;
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
        $token = Token::create('somePersonId', true, 'cus_aaaaaaaaaaaa11');

        $this->assertMatchesRegularExpression('/^[^.]+\.[^.]+\.[^.]+$/', $token);
    }

    public function testCheckPassesWhenAllValid(): void
    {
        $token = Token::create('somePersonId', true, 'cus_aaaaaaaaaaaa11');

        $this->assertTrue(Token::check('somePersonId', true, $token, new NullLogger()));
    }

    public function testCheckFailsWhenWrongPersonId(): void
    {
        $token = Token::create('somePersonId', true, 'cus_aaaaaaaaaaaa11');

        $this->assertFalse(Token::check('someOtherPersonId', true, $token, new NullLogger()));
    }

    public function testCheckFailsWhenSignatureGarbled(): void
    {
        $token = Token::create('somePersonId', true, 'cus_aaaaaaaaaaaa11');

        $this->assertFalse(Token::check('somePersonId', true, $token . 'X', new NullLogger()));
    }

    public function testCheckFailsWithWrongCompletenessFlag(): void
    {
        $token = Token::create('somePersonId', false, 'cus_aaaaaaaaaaaa11');

        $this->assertFalse(Token::check('somePersonId', true, $token, new NullLogger()));
    }

    public function testTokenValidFor7Hours59(): void
    {
        $token = Token::create('somePersonId', true, 'cus_aaaaaaaaaaaa11');

        JWT::$timestamp = (new \DateTimeImmutable('+ 7 hours 59 minutes'))->getTimestamp();

        $this->assertTrue(Token::check('somePersonId', true, $token, new NullLogger()));
    }
    public function testTokenExpiresInEightHours(): void
    {
        $str = Token::create('somePersonId', true, 'cus_aaaaaaaaaaaa11');

        JWT::$timestamp = (new \DateTimeImmutable('+ 8 hours'))->getTimestamp();

        $this->assertFalse(Token::check('somePersonId', true, $str, new NullLogger()));
    }
}
