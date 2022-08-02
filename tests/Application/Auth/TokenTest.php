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
        $token = Token::create('somePersonId');

        $this->assertMatchesRegularExpression('/^[^.]+\.[^.]+\.[^.]+$/', $token);
    }

    public function testCheckPassesWhenAllValid(): void
    {
        $token = Token::create('somePersonId');

        $this->assertTrue(Token::check('somePersonId', $token, new NullLogger()));
    }

    public function testCheckFailsWhenWrongPersonId(): void
    {
        $token = Token::create('somePersonId');

        $this->assertFalse(Token::check('someOtherPersonId', $token, new NullLogger()));
    }

    public function testCheckFailsWhenSignatureGarbled(): void
    {
        $token = Token::create('somePersonId');

        $this->assertFalse(Token::check('somePersonId', $token . 'X', new NullLogger()));
    }
}
