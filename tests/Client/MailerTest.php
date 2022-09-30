<?php

declare(strict_types=1);

namespace BigGive\Identity\Tests\Client;

use DI\Container;
use BigGive\Identity\Application\Settings\SettingsInterface;
use BigGive\Identity\Client\Mailer;
use BigGive\Identity\Tests\TestCase;
use BigGive\Identity\Tests\TestPeopleTrait;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

class MailerTest extends TestCase
{
    use TestPeopleTrait;

    public function testSuccessfulSendMail(): void
    {
        $app = $this->getAppInstance();
        /** @var Container $container */
        $container = $app->getContainer();
        $settings = $container->get(SettingsInterface::class);

        $string = json_encode([
            'status' => 'queued',
            'id' => 'f7095caf-7180-4ddf-a212-44bacde69066'
        ]);
        $mockedResponse = new Response(200, ['Content-Type' => 'application/json'], $string);

        $personWithPostPersistData = $this->getInitialisedPerson(false);
        $requestBody = $personWithPostPersistData->toMailerPayload();

        $clientProphecy = $this->prophesize(Client::class);
        $clientProphecy->post(
            $settings->get('apiClient')['mailer']['baseUri'] . '/v1/send',
            [
                'json' => $requestBody,
                'headers' => [
                    'x-send-verify-hash' => 'f47f4a3a0b898619d6402620f9b0f521cad714370e188e90b7529a2a9b85ffd5',
                ],
            ]
        )->shouldBeCalledOnce()
        ->willReturn($mockedResponse);

        $container->set(Client::class, $clientProphecy->reveal());

        // Get DI client after it's been set, so the get below uses the mocked Guzzle client instead of the real one
        $mailerClient = $container->get(Mailer::class);

        $sendSuccessful = $mailerClient->sendEmail($requestBody);
        $this->assertEquals(true, $sendSuccessful);
    }

    public function testFailedSendMailDueTo400(): void
    {
        $app = $this->getAppInstance();
        $container = $app->getContainer();
        $settings = $container->get(SettingsInterface::class);

        $mockedResponse = new Response(400, ['Content-Type' => 'application/json'], json_encode([
            'status' => 'failed'
        ]));

        $personWithPostPersistData = $this->getInitialisedPerson(false);
        $requestBody = $personWithPostPersistData->toMailerPayload();

        $clientProphecy = $this->prophesize(Client::class);
        $clientProphecy->post(
            $settings->get('apiClient')['mailer']['baseUri'] . '/v1/send',
            [
                'json' => $requestBody,
                'headers' => [
                    'x-send-verify-hash' => 'f47f4a3a0b898619d6402620f9b0f521cad714370e188e90b7529a2a9b85ffd5',
                ],
            ]
        )->shouldBeCalledOnce()
        ->willReturn($mockedResponse);

        $container->set(Client::class, $clientProphecy->reveal());

        // Get DI client after it's been set, so the get below uses the mocked Guzzle client instead of the real one
        $mailerClient = $container->get(Mailer::class);

        $sendSuccessful = $mailerClient->sendEmail($requestBody);

        // assert false
        $this->assertEquals(false, $sendSuccessful);
    }

    public function testFailedSendMailDueToGuzzleException(): void
    {
        $app = $this->getAppInstance();
        $container = $app->getContainer();
        $settings = $container->get(SettingsInterface::class);

        $personWithPostPersistData = $this->getInitialisedPerson(false);
        $requestBody = $personWithPostPersistData->toMailerPayload();

        $clientProphecy = $this->prophesize(Client::class);
        $clientProphecy->post(
            $settings->get('apiClient')['mailer']['baseUri'] . '/v1/send',
            [
                'json' => $requestBody,
                'headers' => [
                    'x-send-verify-hash' => 'f47f4a3a0b898619d6402620f9b0f521cad714370e188e90b7529a2a9b85ffd5',
                ],
            ]
        )->shouldBeCalledOnce()
        ->willThrow(
            new BadResponseException( // BadResponseException is a type of GuzzleException
                'Mocked exception thrown',
                new Request(
                    'POST',
                    $settings->get('apiClient')['mailer']['baseUri'] . '/v1/send'
                ),
                new Response(404)
            )
        );

        $container->set(Client::class, $clientProphecy->reveal());

        // Get DI client after it's been set, so the get below uses the mocked Guzzle client instead of the real one
        $mailerClient = $container->get(Mailer::class);

        $sendSuccessful = $mailerClient->sendEmail($requestBody);

        // assert false
        $this->assertEquals(false, $sendSuccessful);
    }

    public function testFailedSendMailDueToRequestException(): void
    {
        $app = $this->getAppInstance();
        $container = $app->getContainer();
        $settings = $container->get(SettingsInterface::class);

        $personWithPostPersistData = $this->getInitialisedPerson(false);
        $requestBody = $personWithPostPersistData->toMailerPayload();

        $clientProphecy = $this->prophesize(Client::class);
        $clientProphecy->post(
            $settings->get('apiClient')['mailer']['baseUri'] . '/v1/send',
            [
                'json' => $requestBody,
                'headers' => [
                    'x-send-verify-hash' => 'f47f4a3a0b898619d6402620f9b0f521cad714370e188e90b7529a2a9b85ffd5',
                ],
            ]
        )->shouldBeCalledOnce()
        ->willThrow(
            new RequestException(
                'Mocked exception thrown',
                new Request(
                    'POST',
                    $settings->get('apiClient')['mailer']['baseUri'] . '/v1/send'
                )
            )
        );

        $container->set(Client::class, $clientProphecy->reveal());

        // Get DI client after it's been set, so the get below uses the mocked Guzzle client instead of the real one
        $mailerClient = $container->get(Mailer::class);

        $sendSuccessful = $mailerClient->sendEmail($requestBody);

        // assert false
        $this->assertEquals(false, $sendSuccessful);
    }
}
