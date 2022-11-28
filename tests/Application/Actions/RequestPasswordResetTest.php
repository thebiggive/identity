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

class RequestPasswordResetTest extends TestCase
{
    use ProphecyTrait;

    public function testRequestingPasswordResetStoresToken(): void
    {
        // arrange
        $app = $this->getAppInstance();

        $personRepoProphecy = $this->prophesize(PersonRepository::class);
        $personRepoProphecy->findPasswordEnabledPersonByEmailAddress('donor@weliketodonatebutweforgotpasswords.com')
            ->willReturn(new Person());

        $passwordResetTokenProphecy = $this->prophesize(PasswordResetTokenRepository::class);
        $container = $app->getContainer();
        assert($container instanceof Container);
        $container->set(PasswordResetTokenRepository::class, $passwordResetTokenProphecy->reveal());
        $container->set(PersonRepository::class, $personRepoProphecy->reveal());


        // assert
        $passwordResetTokenProphecy->persist(Argument::type(PasswordResetToken::class))->shouldBeCalledOnce();

        // act
        $app->handle($this->buildRequest([
            'email_address' => 'donor@weliketodonatebutweforgotpasswords.com',
        ]));
    }

    private function buildRequest(array $payload): ServerRequestInterface
    {
        // Accept JSON is the `createRequest()` default.
        $request = $this->createRequest('POST', '/v1/password-reset-token');
        $request->getBody()->write(json_encode(
            $payload,
            JSON_THROW_ON_ERROR,
        ));

        return $request;
    }
}