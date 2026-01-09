<?php

namespace BigGive\Identity\IntegrationTests;

use BigGive\Identity\Application\Auth\TokenService;
use BigGive\Identity\Domain\Person;
use BigGive\Identity\Repository\PersonRepository;
use GuzzleHttp\Psr7\ServerRequest;
use Prophecy\Argument;
use Slim\Exception\HttpNotFoundException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\RoutableMessageBus;
use Symfony\Component\Uid\Uuid;

class DeletePersonTest extends IntegrationTest
{
    public function testItDeletesAPersonRecord(): void
    {
        $secret = getenv('JWT_ID_SECRET');
        \assert(\is_string($secret));
        $tokenService = new TokenService([$secret]);

        $uuid = $this->addPersonToToDB(
            emailAddress: "someemail" . Uuid::v4() . "@example.com",
            password: 'topsecret',
        )->toRfc4122();

        $this->getApp()->handle(new ServerRequest(
            method: 'DELETE',
            uri: "/v1/people/{$uuid}",
            headers: ['x-tbg-auth' => $tokenService->create(new \DateTimeImmutable(), $uuid, true, '')],
            body: '{"password": "topsecret"}',
        ));

        try {
            $this->getApp()->handle(new ServerRequest(
                method: 'GET',
                uri: "/v1/people/{$uuid}",
                headers: ['x-tbg-auth' => $tokenService->create(new \DateTimeImmutable(), $uuid, true, '')],
            ));

            $this->fail('Person should not be found after deletion');
        } catch (HttpNotFoundException $exception) {
            $this->assertSame($exception->getMessage(), 'Person not found');
        }
    }
}
