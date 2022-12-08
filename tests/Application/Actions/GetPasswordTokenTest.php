<?php

namespace BigGive\Identity\Tests\Application\Actions;

use BigGive\Identity\Domain\PasswordResetToken;
use BigGive\Identity\Domain\Person;
use BigGive\Identity\Repository\PasswordResetTokenRepository;
use BigGive\Identity\Repository\PersonRepository;
use BigGive\Identity\Tests\TestCase;
use DI\Container;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpBadRequestException;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV4;

class GetPasswordTokenTest extends TestCase
{
    use ProphecyTrait;

    public function testCanChangePassword(): void
    {
        // arrange
        $base58Token = 'XT6A2y3ZedFerQnRnLFLsS';
        $secret = Uuid::fromBase58($base58Token);
        $passwordResetToken = PasswordResetToken::fromBase58(new Person(), $base58Token);

        $passwordResetTokenRepoProphecy = $this->prophesize(PasswordResetTokenRepository::class);
        $passwordResetTokenRepoProphecy->findForUse($secret)->willReturn($passwordResetToken);

        $app = $this->getAppInstance();
        $container = $app->getContainer();
        assert($container instanceof Container);
        $container->set(PasswordResetTokenRepository::class, $passwordResetTokenRepoProphecy->reveal());

        // act
        $response = $app->handle($this->createRequest('GET', "/v1/password-reset-token/$base58Token"));

        // assert
        $this->assertSame(200, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString('{"valid": true}', $response->getBody()->getContents());
    }
}
