<?php

declare(strict_types=1);

namespace BigGive\Identity\Tests\Application\Actions;

use BigGive\Identity\Application\Middleware\FriendlyCaptchaVerifier;
use BigGive\Identity\Application\Security\Password;
use BigGive\Identity\Domain\Person;
use BigGive\Identity\Repository\PersonRepository;
use BigGive\Identity\Tests\TestCase;
use BigGive\Identity\Tests\TestPeopleTrait;
use Prophecy\Argument;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpUnauthorizedException;

class LoginTest extends TestCase
{
    use TestPeopleTrait;

    public function testSuccess(): void
    {
        $person = $this->getInitialisedPerson(true);

        $app = $this->getAppInstance();

        $personRepoProphecy = $this->prophesize(PersonRepository::class);
        $personRepoProphecy->findPasswordEnabledPersonByEmailAddress($person->email_address)
            ->shouldBeCalledOnce()
            ->willReturn($person);

        $personRepoProphecy->upgradePasswordIfPossible(
            $person->raw_password,
            $person
        )
            ->shouldBeCalledOnce();
        $personRepoProphecy->persist(Argument::type(Person::class), true)->shouldBeCalledOnce();

        $this->getContainer()->set(PersonRepository::class, $personRepoProphecy->reveal());

        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.1';

        $request = $this->buildRequest([
            'captcha_code' => 'good response',
            'email_address' => $person->email_address,
            'raw_password' => $person->raw_password,
        ]);

        $response = $app->handle($request);
        $payloadJSON = (string) $response->getBody();

        unset($_SERVER['HTTP_X_FORWARDED_FOR']);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertJson($payloadJSON);

        $payload = json_decode($payloadJSON, false, 512, JSON_THROW_ON_ERROR);

        $this->assertIsString($payload->jwt);
        $this->assertIsString($payload->id);
        $this->assertNotEmpty($payload->jwt);
        $this->assertNotEmpty($payload->id);
        $this->assertEquals('198.51.100.1', $person->lastLoginIP);
        $this->assertEquals(0, $person->failedLoginAttempts);
        $this->assertNull($person->lastFailedLoginTime);
    }

    public function testNoUserFound(): void
    {
        $person = $this->getTestPerson();

        $app = $this->getAppInstance();

        $personRepoProphecy = $this->prophesize(PersonRepository::class);
        $personRepoProphecy->findPasswordEnabledPersonByEmailAddress($person->email_address)
            ->shouldBeCalledOnce()
            ->willReturn(null);

        $this->getContainer()->set(PersonRepository::class, $personRepoProphecy->reveal());

        $request = $this->buildRequest([
            'captcha_code' => 'good response',
            'email_address' => $person->email_address,
            'raw_password' => 'notThisOne',
        ]);

        $response = $app->handle($request);
        $payloadJSON = (string) $response->getBody();

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertJson($payloadJSON);

        $expectedJSON = json_encode([
            'error' => [
                'description' => 'Your email or password is incorrect',
                'type' => 'VALIDATION_ERROR',
            ],
        ], JSON_THROW_ON_ERROR);
        $this->assertJsonStringEqualsJsonString($expectedJSON, $payloadJSON);
    }

    public function testIncorrectPassword(): void
    {
        $person = $this->getInitialisedPerson(true);

        $app = $this->getAppInstance();

        $personRepoProphecy = $this->prophesize(PersonRepository::class);
        $personRepoProphecy->findPasswordEnabledPersonByEmailAddress($person->email_address)
            ->shouldBeCalledOnce()
            ->willReturn($person);

        $personRepoProphecy->persist($person, true)->shouldBeCalledOnce();

        $this->getContainer()->set(PersonRepository::class, $personRepoProphecy->reveal());

        $request = $this->buildRequest([
            'captcha_code' => 'good response',
            'email_address' => $person->email_address,
            'raw_password' => 'notThisOne',
        ]);

        $response = $app->handle($request);
        $payloadJSON = (string) $response->getBody();

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertJson($payloadJSON);

        $expectedJSON = json_encode([
            'error' => [
                'description' => 'Your email or password is incorrect',
                'type' => 'VALIDATION_ERROR',
            ],
        ], JSON_THROW_ON_ERROR);
        $this->assertJsonStringEqualsJsonString($expectedJSON, $payloadJSON);
        $this->assertEquals(1, $person->failedLoginAttempts);
    }

    public function testLockoutEnforcedForNewIp(): void
    {
        $person = $this->getInitialisedPerson(true);
        $person->lastLoginIP = '198.51.100.1';
        $person->failedLoginAttempts = 4;
        $person->lastFailedLoginTime = new \DateTimeImmutable();

        $app = $this->getAppInstance();

        $personRepoProphecy = $this->prophesize(PersonRepository::class);
        $personRepoProphecy->findPasswordEnabledPersonByEmailAddress($person->email_address)
            ->shouldBeCalledOnce()
            ->willReturn($person);

        // Should NOT persist or attempt password verification because account is locked out for new IP
        $personRepoProphecy->persist(Argument::any(), Argument::any())->shouldNotBeCalled();

        $this->getContainer()->set(PersonRepository::class, $personRepoProphecy->reveal());

        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.5'; // New / attacker IP

        $request = $this->buildRequest([
            'captcha_code' => 'good response',
            'email_address' => $person->email_address,
            'raw_password' => 'somePassword',
        ]);

        $response = $app->handle($request);
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);

        $payloadJSON = (string) $response->getBody();

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertJson($payloadJSON);

        $expectedJSON = json_encode([
            'error' => [
                'description' => Password::ACCOUNT_LOCKED_MESSAGE,
                'type' => 'VALIDATION_ERROR',
            ],
        ], JSON_THROW_ON_ERROR);
        $this->assertJsonStringEqualsJsonString($expectedJSON, $payloadJSON);
    }

    public function testLockoutBypassedForKnownSameIp(): void
    {
        $person = $this->getInitialisedPerson(true);
        $person->lastLoginIP = '198.51.100.1';
        $person->failedLoginAttempts = 4;
        $failedTime = new \DateTimeImmutable();
        $person->lastFailedLoginTime = $failedTime;

        $app = $this->getAppInstance();

        $personRepoProphecy = $this->prophesize(PersonRepository::class);
        $personRepoProphecy->findPasswordEnabledPersonByEmailAddress($person->email_address)
            ->shouldBeCalledOnce()
            ->willReturn($person);

        $personRepoProphecy->upgradePasswordIfPossible(
            $person->raw_password,
            $person
        )->shouldBeCalledOnce();
        $personRepoProphecy->persist($person, true)->shouldBeCalledOnce();

        $this->getContainer()->set(PersonRepository::class, $personRepoProphecy->reveal());

        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.1'; // Same known IP

        $request = $this->buildRequest([
            'captcha_code' => 'good response',
            'email_address' => $person->email_address,
            'raw_password' => $person->raw_password,
        ]);

        $response = $app->handle($request);
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);

        $payloadJSON = (string) $response->getBody();

        $this->assertEquals(200, $response->getStatusCode());
        // Knowing user is at home IP, failures are preserved against attacker IPs
        $this->assertEquals(4, $person->failedLoginAttempts);
        $this->assertEquals($failedTime, $person->lastFailedLoginTime);
    }

    public function testSuccessfulLoginFromNewIpResetsFailures(): void
    {
        $person = $this->getInitialisedPerson(true);
        $person->lastLoginIP = '198.51.100.1';
        $person->failedLoginAttempts = 2;
        $person->lastFailedLoginTime = new \DateTimeImmutable();

        $app = $this->getAppInstance();

        $personRepoProphecy = $this->prophesize(PersonRepository::class);
        $personRepoProphecy->findPasswordEnabledPersonByEmailAddress($person->email_address)
            ->shouldBeCalledOnce()
            ->willReturn($person);

        $personRepoProphecy->upgradePasswordIfPossible(
            $person->raw_password,
            $person
        )->shouldBeCalledOnce();
        $personRepoProphecy->persist($person, true)->shouldBeCalledOnce();

        $this->getContainer()->set(PersonRepository::class, $personRepoProphecy->reveal());

        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.2'; // New IP

        $request = $this->buildRequest([
            'captcha_code' => 'good response',
            'email_address' => $person->email_address,
            'raw_password' => $person->raw_password,
        ]);

        $response = $app->handle($request);
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);

        $payloadJSON = (string) $response->getBody();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('198.51.100.2', $person->lastLoginIP);
        $this->assertEquals(0, $person->failedLoginAttempts);
        $this->assertNull($person->lastFailedLoginTime);
    }

    public function testFailingCaptcha(): void
    {
        $this->expectException(HttpUnauthorizedException::class);
        $this->expectExceptionMessage('Unauthorised');

        $person = $this->getTestPerson();

        $app = $this->getAppInstance();
        $this->getContainer()->set(
            FriendlyCaptchaVerifier::class,
            new class extends FriendlyCaptchaVerifier {
                public function __construct()
                {
                }
                public function verify(string $solution): bool
                {
                    return false;
                }
            }
        );

        $personWithHashNotMatchingRawPassword = $this->prophesize(Person::class);
        $personWithHashNotMatchingRawPassword->getPasswordHash()
            ->shouldNotBeCalled();

        $personRepoProphecy = $this->prophesize(PersonRepository::class);
        $personRepoProphecy->findPasswordEnabledPersonByEmailAddress($person->email_address)
            ->shouldNotBeCalled();

        $this->getContainer()->set(PersonRepository::class, $personRepoProphecy->reveal());

        $request = $this->buildRequest([
            'captcha_code' => 'bad response',
            'email_address' => $person->email_address,
            'raw_password' => 'notThisOne',
        ]);

        $app->handle($request);
    }

    private function buildRequest(array $payload): ServerRequestInterface
    {
        // Accept JSON is the `createRequest()` default.
        $request = $this->createRequest('POST', '/v1/auth');
        $request->getBody()->write(json_encode(
            $payload,
            JSON_THROW_ON_ERROR,
        ));

        return $request;
    }
}
