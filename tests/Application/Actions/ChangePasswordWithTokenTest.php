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
        $person = new Person();

        $passwordResetTokenProphecy = $this->prophesize(PasswordResetTokenRepository::class);
        $passwordResetToken = PasswordResetToken::random($person);
        $passwordResetToken->created_at = new \DateTime("59 minutes ago"); // almost expired
        $passwordResetTokenProphecy->findForUse($secret)->willReturn($passwordResetToken);
        $passwordResetTokenProphecy->persist($passwordResetToken)->shouldBeCalled();

        $personRepoProphecy = $this->prophesize(PersonRepository::class);
        $personRepoProphecy->find($personId->toRfc4122())->willReturn($person);

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
            'new_password' => 'n3w-p4ssw0rd',
        ]));

        $this->assertTrue($passwordResetToken->isUsed());
    }

    public function testCannotChangePasswordUsingExpiredToken(): void
    {
        // may not be worth doing now the test is written, but we could simplify by testing just PasswordResetToken class directly instead of the whole app.

        $secret = Uuid::v4();
        $personId = Uuid::v4();
        $person = new Person();

        $passwordResetToken = PasswordResetToken::random($person);
        $passwordResetToken->created_at = new \DateTime("62 minutes ago");

        $passwordResetTokenProphecy = $this->prophesize(PasswordResetTokenRepository::class);

        $passwordResetTokenProphecy->findForUse($secret)->willReturn($passwordResetToken);

        $personRepoProphecy = $this->prophesize(PersonRepository::class);
        $personRepoProphecy->find($personId->toRfc4122())->willReturn($person);

        $app = $this->getAppInstance();
        $container = $app->getContainer();
        assert($container instanceof Container);
        $container->set(PasswordResetTokenRepository::class, $passwordResetTokenProphecy->reveal());
        $container->set(PersonRepository::class, $personRepoProphecy->reveal());

        $personRepoProphecy->persistForPasswordChange(Argument::any())->shouldNotBeCalled();

        $this->expectExceptionMessage('Token expired');
        $app->handle($this->buildRequest([
            'secret' => $secret->toBase58(),
            'new_password' => 'n3w-p4ssw0rd',
        ]));
    }

    public function testCannotChangePasswordTwiceWithSameToken(): void
    {
        //  may not be worth doing now the test is written, but we could simplify by testing just PasswordResetToken class directly instead of the whole app.
        $secret = Uuid::v4();
        $personId = Uuid::v4();
        $person = new Person();

        $passwordResetTokenProphecy = $this->prophesize(PasswordResetTokenRepository::class);
        $passwordResetToken = PasswordResetToken::random($person);
        $passwordResetToken->created_at = new \DateTime("59 minutes ago"); // almost expired
        $passwordResetTokenProphecy->findForUse($secret)->willReturn($passwordResetToken);
        $passwordResetTokenProphecy->persist($passwordResetToken)->shouldBeCalled();


        $personRepoProphecy = $this->prophesize(PersonRepository::class);
        $personRepoProphecy->find($personId->toRfc4122())->willReturn($person);

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
            'new_password' => 'n3w-p4ssw0rd',
        ]));

        $this->expectExceptionMessage('Token already used');
        $app->handle($this->buildRequest([
            'secret' => $secret->toBase58(),
            'new_password' => 's3cond-n3w-p4ssw0rd',
        ]));
    }

    public function testCannotSetPasswordShorterThanMinLength(): void
    {
        //  may not be worth doing now the test is written, but we could simplify by testing just PasswordResetToken class directly instead of the whole app.
        $secret = Uuid::v4();
        $personId = Uuid::v4();
        $person = new Person();

        $passwordResetTokenProphecy = $this->prophesize(PasswordResetTokenRepository::class);
        $passwordResetToken = PasswordResetToken::random($person);
        $passwordResetToken->created_at = new \DateTime("59 minutes ago"); // almost expired
        $passwordResetTokenProphecy->findForUse($secret)->willReturn($passwordResetToken);

        $personRepoProphecy = $this->prophesize(PersonRepository::class);
        $personRepoProphecy->find($personId->toRfc4122())->willReturn($person);

        $app = $this->getAppInstance();
        $container = $app->getContainer();
        assert($container instanceof Container);
        $container->set(PasswordResetTokenRepository::class, $passwordResetTokenProphecy->reveal());
        $container->set(PersonRepository::class, $personRepoProphecy->reveal());

        $this->expectExceptionMessage('Password must be 10 or more characters');
        $app->handle($this->buildRequest([
            'secret' => $secret->toBase58(),
            'new_password' => 'short',
        ]));
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
