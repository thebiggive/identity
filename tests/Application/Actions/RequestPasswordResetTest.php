<?php

namespace BigGive\Identity\Tests\Application\Actions;

use BigGive\Identity\Client\Mailer;
use BigGive\Identity\Domain\PasswordResetToken;
use BigGive\Identity\Domain\Person;
use BigGive\Identity\Repository\PasswordResetTokenRepository;
use BigGive\Identity\Repository\PersonRepository;
use BigGive\Identity\Tests\TestCase;
use DI\Container;
use Laminas\Diactoros\ServerRequest;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\Uid\Uuid;

class RequestPasswordResetTest extends TestCase
{
    use ProphecyTrait;

    public function testRequestingPasswordResetStoresToken(): void
    {
        $app = $this->getAppInstance();
        $personRepoProphecy = $this->prophesize(PersonRepository::class);
        $personId = Uuid::v4();
        $emailAddress = 'donor@weliketodonatebutweforgotpasswords.com';

        // arrange
        $person = new Person();
        $person->setId($personId);

        $personRepoProphecy->findPasswordEnabledPersonByEmailAddress($emailAddress)
            ->willReturn($person);

        $passwordResetTokenProphecy = $this->prophesize(PasswordResetTokenRepository::class);

        $mailerProphecy = $this->prophesize(Mailer::class);
        $mailerProphecy->sendEmail(Argument::any())->willReturn(true);

        $container = $app->getContainer();
        assert($container instanceof Container);
        $container->set(PasswordResetTokenRepository::class, $passwordResetTokenProphecy->reveal());
        $container->set(PersonRepository::class, $personRepoProphecy->reveal());
        $container->set(Mailer::class, $mailerProphecy->reveal());

        // asert

        $passwordResetTokenProphecy->persist(Argument::that(function (PasswordResetToken $token) use ($person){
            return $token->person === $person;
        }))->shouldBeCalledOnce();

        // act
        $app->handle($this->buildRequest([
            'email_address' => $emailAddress,
        ]));
    }

    public function testRequestingPasswordResetSendsEmail(): void
    {
        $app = $this->getAppInstance();
        $personRepoProphecy = $this->prophesize(PersonRepository::class);
        $personId = Uuid::v4();
        $emailAddress = 'donor@weliketodonatebutweforgotpasswords.com';

        // arrange
        $person = new Person();
        $person->setId($personId);
        $person->email_address = $emailAddress;
        $person->first_name = "Joe";
        $person->last_name = "Bloggs";

        $personRepoProphecy->findPasswordEnabledPersonByEmailAddress($emailAddress)
            ->willReturn($person);

        $passwordResetTokenProphecy = $this->prophesize(PasswordResetTokenRepository::class);
        $container = $app->getContainer();
        assert($container instanceof Container);
        $container->set(PasswordResetTokenRepository::class, $passwordResetTokenProphecy->reveal());
        $container->set(PersonRepository::class, $personRepoProphecy->reveal());

        // assert
        $mailerProphecy = $this->prophesize(Mailer::class);
        $container->set(Mailer::class, $mailerProphecy->reveal());

        $mailerProphecy->sendEmail(Argument::that(function (array $params) use ($emailAddress) {
            /** @var array<string, string> $params */
            $this->assertSame('password-reset-requested', $params['templateKey']);
            $this->assertSame($emailAddress, $params['recipientEmailAddress']);
            $this->assertMatchesRegularExpression('/http.*/', $params['params']['resetLink']);
            $this->assertSame('Joe', $params['params']['firstName']);
            $this->assertSame('Bloggs', $params['params']['lastName']);

            return true;
        }))->shouldBeCalledOnce();

        // act
        $app->handle($this->buildRequest([
            'email_address' => $emailAddress,
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
