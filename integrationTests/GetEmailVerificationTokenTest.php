<?php

namespace BigGive\Identity\IntegrationTests;

use BigGive\Identity\Application\Security\EmailVerificationService;
use BigGive\Identity\Domain\EmailVerificationToken;
use BigGive\Identity\Domain\Person;
use BigGive\Identity\Repository\PersonRepository;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Psr7\ServerRequest;
use Prophecy\Argument;
use Slim\Exception\HttpNotFoundException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\RoutableMessageBus;
use Symfony\Component\Uid\Uuid;

class GetEmailVerificationTokenTest extends IntegrationTest
{
    private string $emailAddress;
    private string $personUUID;
    private EmailVerificationToken $token;

    public function setUp(): void
    {
        parent::setUp();
        $this->emailAddress = "someemail" . Uuid::v4() . "@example.com";

        $this->personUUID = $this->addPersonToToDB($this->emailAddress)->toRfc4122();

        $this->getService(EmailVerificationService::class)->storeTokenForEmail($this->emailAddress);

        $this->token = $this->getService(EntityManagerInterface::class)->getRepository(EmailVerificationToken::class)
            ->findOneBy(['email_address' => $this->emailAddress]) ?? throw new \Exception("token not found");
    }
    public function testItReturnsEmailVerificationToken(): void
    {
        $response = $this->getApp()->handle(new ServerRequest(
            method: 'GET',
            uri: "/v1/emailVerificationToken/{$this->token->random_code}/{$this->personUUID}",
        ));

        /** @var array{token: array<string, string|bool>} $decodedBody */
        $decodedBody = json_decode($response->getBody()->getContents(), true);

        $this->assertSame($this->emailAddress, $decodedBody['token']['email_address']);
        $this->assertSame('Fred', $decodedBody['token']['first_name']);
        $this->assertSame('Bloggs', $decodedBody['token']['last_name']);
        $this->assertTrue($decodedBody['token']['valid']);
    }

    public function testItRejectsWrongSecret(): void
    {
        $this->expectException(HttpNotFoundException::class);
        $this->getApp()->handle(new ServerRequest(
            method: 'GET',
            uri: "/v1/emailVerificationToken/123456/{$this->personUUID}",
        ));
    }

    public function testItRejectsOldToken(): void
    {
        $this->token->created_at = $this->token->created_at->modify('-8 hours');
        $this->getService(EntityManagerInterface::class)->flush();

        $this->expectException(HttpNotFoundException::class);
        $this->getApp()->handle(new ServerRequest(
            method: 'GET',
            uri: "/v1/emailVerificationToken/{$this->token->random_code}/{$this->personUUID}",
        ));
    }
}
