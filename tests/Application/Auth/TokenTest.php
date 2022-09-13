<?php

declare(strict_types=1);

namespace BigGive\Identity\Tests\Application\Auth;

use BigGive\Identity\Application\Auth\Token;
use BigGive\Identity\Tests\TestCase;
use Psr\Log\NullLogger;

class TokenTest extends TestCase
{
    public function testCreateReturnsValidLookingToken(): void
    {
        $token = Token::create('somePersonId', true);

        $this->assertMatchesRegularExpression('/^[^.]+\.[^.]+\.[^.]+$/', $token);
    }

    public function testCheckPassesWhenAllValid(): void
    {
        $token = Token::create('somePersonId', true);

        $this->assertTrue(Token::check('somePersonId', true, $token, new NullLogger()));
    }

    public function testCheckFailsWhenWrongPersonId(): void
    {
        $token = Token::create('somePersonId', true);

        $this->assertFalse(Token::check('someOtherPersonId', true, $token, new NullLogger()));
    }

    public function testCheckFailsWhenSignatureGarbled(): void
    {
        $token = Token::create('somePersonId', true);

        $this->assertFalse(Token::check('somePersonId', true, $token . 'X', new NullLogger()));
    }

    public function testCheckFailsWithWrongCompletenessFlag(): void
    {
        $token = Token::create('somePersonId', false);

        $this->assertFalse(Token::check('somePersonId', true, $token, new NullLogger()));
    }
}
