<?php

declare(strict_types=1);

namespace BigGive\Identity\Tests\Domain;

use BigGive\Identity\Domain\Person;
use BigGive\Identity\Tests\TestCase;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Uid\Uuid;

class PersonTest extends TestCase
{
    private const string CUSTOMER_NAME = 'Loraine James';

    public function testGetters(): void
    {
        $person = $this->getPersonWithKeyFieldsSet();

        // Cast to string to use symfony/uid's default normalisation.
        $this->assertEquals(36, strlen((string) $person->getId()));
        $this->assertEquals('Loraine', $person->getFirstName());
        $this->assertEquals('James', $person->getLastName());
    }

    public function testJsonSerialize(): void
    {
        $person = $this->getPersonWithKeyFieldsSet();
        $json = $this->getAppInstance()->getContainer()->get(SerializerInterface::class)
            ->serialize($person, 'json');
        $jsonData = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $this->assertEquals('Loraine', $jsonData['first_name']);
        $this->assertEquals('James', $jsonData['last_name']);
        $this->assertEquals('loraine@hyperdub.example.net', $jsonData['email_address']);
    }

    public function testToMailerPayload(): void
    {
        $person = $this->getPersonWithKeyFieldsSet();
        $payload = $person->toMailerPayload();

        $expectedPayload = [
            'templateKey' => 'donor-registered',
            'recipientEmailAddress' => 'loraine@hyperdub.example.net',
            'forGlobalCampaign' => false,
            'params' => [
                'donorFirstName' => 'Loraine',
                'donorEmail' => 'loraine@hyperdub.example.net',
            ],
        ];

        $this->assertEquals($expectedPayload, $payload);
    }

    public function testToMatchBotSummaryMessage(): void
    {
        $person = $this->getPersonWithKeyFieldsSet();
        $message = $person->toMatchBotSummaryMessage();

        $this->assertInstanceOf(\Messages\Person::class, $message);
        $this->assertEquals('Loraine', $message->first_name);
        $this->assertEquals('James', $message->last_name);
        $this->assertEquals('loraine@hyperdub.example.net', $message->email_address);
        $this->assertEquals('cus_1234567890', $message->stripe_customer_id);
    }

    public function testToStripeCustomerWithPasswordJustSet(): void
    {
        $now = new \DateTimeImmutable('now');

        $person = $this->getPersonWithKeyFieldsSet();
        // For new people, the point of verification of the email address is when they have a usable
        // passworded account, so we send Stripe that timestamp.
        $person->email_address_verified = $now;

        $message = $person->getStripeCustomerParams();

        $this->assertEquals([
            'email' => $person->email_address,
            'name' => self::CUSTOMER_NAME,
            'metadata' => [
                'environment' => 'test',
                'personId' => (string) $person->getId(),
                'hasPasswordSince' => $now->format('Y-m-d H:i:s'),
                'emailAddress' => $person->email_address,
            ],
        ], $message);
    }

    public function testToStripeCustomerWithoutPassword(): void
    {
        $person = $this->getPersonWithKeyFieldsSet();
        $message = $person->getStripeCustomerParams();

        $this->assertEquals([
            'email' => $person->email_address,
            'name' => self::CUSTOMER_NAME,
            'metadata' => [
                'environment' => 'test',
                'personId' => (string) $person->getId(),
            ],
        ], $message);
    }

    public function testRecordSuccessfulLoginFromNewIp(): void
    {
        $person = $this->getPersonWithKeyFieldsSet();
        $person->lastLoginIP = '192.168.1.1';
        $person->failedLoginAttempts = 5;
        $person->lastFailedLoginTime = new \DateTimeImmutable('2026-10-19 16:15:00');

        $now = new \DateTimeImmutable('2026-10-19 16:20:00');
        $person->recordSuccessfulLogin('192.168.1.2', $now);

        $this->assertEquals(0, $person->failedLoginAttempts);
        $this->assertNull($person->lastFailedLoginTime);
        $this->assertEquals('192.168.1.2', $person->lastLoginIP);
        $this->assertEquals($now, $person->lastLoginTime);
    }

    public function testRecordSuccessfulLoginFromKnownIpDoesNotClearFailures(): void
    {
        $person = $this->getPersonWithKeyFieldsSet();
        $person->lastLoginIP = '192.168.1.1';
        $person->failedLoginAttempts = 5;
        $failedTime = new \DateTimeImmutable('2026-10-19 16:15:00');
        $person->lastFailedLoginTime = $failedTime;

        $now = new \DateTimeImmutable('2026-10-19 16:20:00');
        $person->recordSuccessfulLogin('192.168.1.1', $now);

        $this->assertEquals(5, $person->failedLoginAttempts);
        $this->assertEquals($failedTime, $person->lastFailedLoginTime);
        $this->assertEquals('192.168.1.1', $person->lastLoginIP);
        $this->assertEquals($now, $person->lastLoginTime);
    }

    public function testRecordFailedLoginWithoutLockoutUnderThreshold(): void
    {
        $person = $this->getPersonWithKeyFieldsSet();
        $now = new \DateTimeImmutable('2026-10-19 16:20:00');

        $this->assertEquals(0, $person->failedLoginAttempts);
        $this->assertNull($person->lastFailedLoginTime);

        $person->recordFailedLogin($now);
        $this->assertEquals(1, $person->failedLoginAttempts);
        $this->assertEquals($now, $person->lastFailedLoginTime);
        $this->assertFalse($person->isLockedOut($now));

        $person->recordFailedLogin($now);
        $this->assertEquals(2, $person->failedLoginAttempts);
        $this->assertFalse($person->isLockedOut($now));

        $person->recordFailedLogin($now);
        $this->assertEquals(3, $person->failedLoginAttempts);
        $this->assertFalse($person->isLockedOut($now));
    }

    public function testRecordFailedLoginExponentialBackoff(): void
    {
        $person = $this->getPersonWithKeyFieldsSet();
        $now = new \DateTimeImmutable('2026-10-19 16:20:00');

        // 3 failures - under threshold
        $person->recordFailedLogin($now);
        $person->recordFailedLogin($now);
        $person->recordFailedLogin($now);
        $this->assertFalse($person->isLockedOut($now));

        // 4th failure: 2^0 * 60 = 60 seconds (1 minute lockout)
        $person->recordFailedLogin($now);
        $this->assertEquals(4, $person->failedLoginAttempts);
        $this->assertTrue($person->isLockedOut($now));
        $this->assertTrue($person->isLockedOut($now->modify('+59 seconds')));
        $this->assertFalse($person->isLockedOut($now->modify('+60 seconds')));

        // 5th failure: 2^1 * 60 = 120 seconds (2 minutes lockout)
        $person->recordFailedLogin($now);
        $this->assertEquals(5, $person->failedLoginAttempts);
        $this->assertTrue($person->isLockedOut($now));
        $this->assertTrue($person->isLockedOut($now->modify('+119 seconds')));
        $this->assertFalse($person->isLockedOut($now->modify('+120 seconds')));

        // 6th failure: 2^2 * 60 = 240 seconds (4 minutes lockout)
        $person->recordFailedLogin($now);
        $this->assertEquals(6, $person->failedLoginAttempts);
        $this->assertTrue($person->isLockedOut($now));
        $this->assertTrue($person->isLockedOut($now->modify('+239 seconds')));
        $this->assertFalse($person->isLockedOut($now->modify('+240 seconds')));

        // 7th failure: 2^3 * 60 = 480 seconds (8 minutes lockout)
        $person->recordFailedLogin($now);
        $this->assertEquals(7, $person->failedLoginAttempts);
        $this->assertTrue($person->isLockedOut($now));
        $this->assertTrue($person->isLockedOut($now->modify('+479 seconds')));
        $this->assertFalse($person->isLockedOut($now->modify('+480 seconds')));
    }

    public function testRecordFailedLoginResetsAfterInactivityWindow(): void
    {
        $person = $this->getPersonWithKeyFieldsSet();
        $firstTime = new \DateTimeImmutable('2026-10-19 12:00:00');

        $person->recordFailedLogin($firstTime);
        $person->recordFailedLogin($firstTime);
        $this->assertEquals(2, $person->failedLoginAttempts);

        // Attempt more than 1 hour later
        $laterTime = new \DateTimeImmutable('2026-10-19 13:00:01');
        $person->recordFailedLogin($laterTime);

        $this->assertEquals(1, $person->failedLoginAttempts);
        $this->assertEquals($laterTime, $person->lastFailedLoginTime);
    }

    public function testIsLockedOutForIp(): void
    {
        $person = $this->getPersonWithKeyFieldsSet();
        $person->lastLoginIP = '203.0.113.1';
        $now = new \DateTimeImmutable('2026-10-19 16:20:00');

        // Not locked out yet
        $this->assertFalse($person->isLockedOutForIp('203.0.113.1', $now));
        $this->assertFalse($person->isLockedOutForIp('198.51.100.99', $now));
        $this->assertFalse($person->isLockedOutForIp(null, $now));

        // 4 failed attempts from now
        $person->failedLoginAttempts = 4;
        $person->lastFailedLoginTime = $now;

        // User at their known home IP is NOT locked out
        $this->assertFalse($person->isLockedOutForIp('203.0.113.1', $now));

        // User / attacker at a new IP IS locked out
        $this->assertTrue($person->isLockedOutForIp('198.51.100.99', $now));
        $this->assertTrue($person->isLockedOutForIp(null, $now));

        // After lockout duration passes (60s), auto-unlocks for new IPs
        $future = $now->modify('+61 seconds');
        $this->assertFalse($person->isLockedOutForIp('198.51.100.99', $future));
    }

    private function getPersonWithKeyFieldsSet(): Person
    {
        $person = new Person();
        $person->setId(Uuid::v4());
        $person->first_name = 'Loraine';
        $person->last_name = 'James';
        $person->email_address = 'loraine@hyperdub.example.net';
        $person->stripe_customer_id = 'cus_1234567890';

        return $person;
    }
}
