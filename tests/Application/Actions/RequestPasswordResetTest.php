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
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV4;

class RequestPasswordResetTest extends TestCase
{
    use ProphecyTrait;

    public function testRequestingPasswordResetStoresToken(): void
    {
        // arrange
        $app = $this->getAppInstance();

        $personRepoProphecy = $this->prophesize(PersonRepository::class);
        $personId = Uuid::v4();
        $person = new Person();
        $person->setId($personId);
        $personRepoProphecy->findPasswordEnabledPersonByEmailAddress('donor@weliketodonatebutweforgotpasswords.com')
            ->willReturn($person);

        $passwordResetTokenProphecy = $this->prophesize(PasswordResetTokenRepository::class);
        $container = $app->getContainer();
        assert($container instanceof Container);
        $container->set(PasswordResetTokenRepository::class, $passwordResetTokenProphecy->reveal());
        $container->set(PersonRepository::class, $personRepoProphecy->reveal());


        // assert
        $passwordResetTokenProphecy->persist(Argument::that(function (PasswordResetToken $token) use ($personId){
            return $token->personId->equals($personId);
        }))->shouldBeCalledOnce();

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