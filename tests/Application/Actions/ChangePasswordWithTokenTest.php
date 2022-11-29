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

class ChangePasswordWithTokenTest extends TestCase
{
    use ProphecyTrait;

    public function testCanChangePassword(): void
    {
        $secret = Uuid::v4();
        $personId = Uuid::v4();

        $passwordResetTokenProphecy = $this->prophesize(PasswordResetTokenRepository::class);
        $passwordResetToken = new PasswordResetToken($personId);
        $passwordResetToken->created_at = new \DateTime("59 minutes ago"); // almost expired
        $passwordResetTokenProphecy->findBySecret($secret)->willReturn($passwordResetToken);

        $personRepoProphecy = $this->prophesize(PersonRepository::class);
        $personRepoProphecy->find($personId->toRfc4122())->willReturn(new Person());

        $app = $this->getAppInstance();
        $container = $app->getContainer();
        assert($container instanceof Container);
        $container->set(PasswordResetTokenRepository::class, $passwordResetTokenProphecy->reveal());
        $container->set(PersonRepository::class, $personRepoProphecy->reveal());

        $personRepoProphecy->persistForPasswordChange(Argument::that(function (Person $person) {
            $this->assertSame('n3w-p4ssw0rd', $person->raw_password);
            return true;
        }))->shouldBeCalledOnce();

        $app->handle($this->buildRequest([
            'secret' => $secret->toBase58(),
            'new-password' => 'n3w-p4ssw0rd',
        ]));
    }

    public function testCannotChangePasswordUsingExpiredToken(): void
    {
        $secret = Uuid::v4();
        $personId = Uuid::v4();
        $passwordResetToken = new PasswordResetToken($personId);
        $passwordResetToken->created_at = new \DateTime("61 minutes ago");

        $passwordResetTokenProphecy = $this->prophesize(PasswordResetTokenRepository::class);

        $passwordResetTokenProphecy->findBySecret($secret)->willReturn($passwordResetToken);

        $personRepoProphecy = $this->prophesize(PersonRepository::class);
        $personRepoProphecy->find($personId->toRfc4122())->willReturn(new Person());

        $app = $this->getAppInstance();
        $container = $app->getContainer();
        assert($container instanceof Container);
        $container->set(PasswordResetTokenRepository::class, $passwordResetTokenProphecy->reveal());
        $container->set(PersonRepository::class, $personRepoProphecy->reveal());

        $personRepoProphecy->persistForPasswordChange(Argument::any())->shouldNotBeCalled();

        try {
            $app->handle($this->buildRequest([
                'secret' => $secret->toBase58(),
                'new-password' => 'n3w-p4ssw0rd',
            ]));
        } catch (HttpBadRequestException $_e) {
            // not sure tbh why I need to catch this here, I would have expected the app to catch it.
        }
    }

    private function buildRequest(array $payload): ServerRequestInterface
    {
        // Accept JSON is the `createRequest()` default.
        $request = $this->createRequest('POST', '/v1/change-forgotten-password');
        $request->getBody()->write(json_encode(
            $payload,
            JSON_THROW_ON_ERROR,
        ));

        return $request;
    }
}
