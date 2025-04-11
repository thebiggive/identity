<?php

namespace BigGive\Identity\IntegrationTests;

use BigGive\Identity\Application\Security\EmailVerificationService;
use BigGive\Identity\Application\Security\Password;
use BigGive\Identity\Domain\EmailVerificationToken;
use BigGive\Identity\Domain\Person;
use BigGive\Identity\Repository\PersonRepository;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Psr7\ServerRequest;
use Slim\Exception\HttpBadRequestException;
use Symfony\Component\Uid\Uuid;

class SetFirstPasswordWithTokenTest extends IntegrationTest
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

    public function testCanSetPassword(): void
    {
        $response = $this->getApp()->handle(new ServerRequest(
            method: 'POST',
            uri: "/v1/people/setFirstPassword",
            body: json_encode([
                'personUuid' => $this->personUUID,
                'secret' => $this->token->random_code,
                'password' => 'p@55w0rd____',
            ], JSON_THROW_ON_ERROR),
        ));

        $this->assertEquals(200, $response->getStatusCode());

        $this->getService(EntityManagerInterface::class)->clear();

        $updatedPerson = $this->getService(PersonRepository::class)->find($this->personUUID);
        \assert($updatedPerson instanceof Person);

        Password::verify('p@55w0rd____', $updatedPerson);
        $this->assertNotNull($updatedPerson->email_address_verified, 'Person should have verified email address');
    }

    public function testCanNotSetPasswordWithWrongCode(): void
    {
        $this->expectException(HttpBadRequestException::class);
        $this->getApp()->handle(new ServerRequest(
            method: 'POST',
            uri: "/v1/people/setFirstPassword",
            body: json_encode([
                'personUuid' => $this->personUUID,
                'secret' => '123456',
                'password' => 'p@55w0rd____',
            ], JSON_THROW_ON_ERROR),
        ));
    }
}
