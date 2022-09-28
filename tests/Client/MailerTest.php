<?php

declare(strict_types=1);

namespace BigGive\Identity\Tests\Client;

use BigGive\Identity\Application\Settings\SettingsInterface;
use BigGive\Identity\Client\Mailer;
use BigGive\Identity\Tests\TestCase;
use BigGive\Identity\Tests\TestPeopleTrait;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\RequestExceptionInterface;

class MailerTest extends TestCase
{
    use TestPeopleTrait;

    public function testSuccessfulSendMail(): void
    {
        $app = $this->getAppInstance();
        $container = $app->getContainer();
        $settings = $container->get(SettingsInterface::class);

        $mailerClient = $container->get(Mailer::class);

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
                    'x-send-verify-hash' => '672132c155bca0f63211da07a70304a3c9eba4c57f4f4702bc220aa85ee04ac8',
                ],
            ]
        )->shouldBeCalledOnce()
        ->willReturn($mockedResponse);

        $container->set(Client::class, $clientProphecy->reveal());

        $sendSuccessful = $mailerClient->sendEmail($requestBody);
        $this->assertEquals(true, $sendSuccessful);
    }

    public function testFailedSendMailDueTo400(): void
    {
        $app = $this->getAppInstance();
        $container = $app->getContainer();
        $settings = $container->get(SettingsInterface::class);

        $mailerClient = $container->get(Mailer::class);

        $mockedResponse = new Response(400, ['Content-Type' => 'application/json'], [
            'status' => 'failed'
        ]);

        $personWithPostPersistData = $this->getInitialisedPerson(false);
        $requestBody = $personWithPostPersistData->toMailerPayload();

        $clientProphecy = $this->prophesize(Client::class);
        $clientProphecy->post(
            $settings->get('apiClient')['mailer']['baseUri'] . '/v1/send',
            [
                'json' => $requestBody,
                'headers' => [
                    'x-send-verify-hash' => '672132c155bca0f63211da07a70304a3c9eba4c57f4f4702bc220aa85ee04ac8',
                ],
            ]
        )->shouldBeCalledOnce()
        ->willReturn($mockedResponse);

        $container->set(Client::class, $clientProphecy->reveal());

        $sendSuccessful = $mailerClient->sendEmail($requestBody);

        // assert false
        $this->assertEquals(false, $sendSuccessful);
    }

    public function testFailedSendMailDueToGuzzleException(): void
    {
        $app = $this->getAppInstance();
        $container = $app->getContainer();
        $settings = $container->get(SettingsInterface::class);

        $mailerClient = $container->get(Mailer::class);

        $personWithPostPersistData = $this->getInitialisedPerson(false);
        $requestBody = $personWithPostPersistData->toMailerPayload();

        $clientProphecy = $this->prophesize(Client::class);
        $clientProphecy->post(
            $settings->get('apiClient')['mailer']['baseUri'] . '/v1/send',
            [
                'json' => $requestBody,
                'headers' => [
                    'x-send-verify-hash' => '672132c155bca0f63211da07a70304a3c9eba4c57f4f4702bc220aa85ee04ac8',
                ],
            ]
        )->shouldBeCalledOnce()
        ->willThrow(GuzzleException::class);

        $container->set(Client::class, $clientProphecy->reveal());

        $sendSuccessful = $mailerClient->sendEmail($requestBody);

        // assert false
        $this->assertEquals(false, $sendSuccessful);
    }

    public function testFailedSendMailDueToRequestException(): void
    {
        $app = $this->getAppInstance();
        $container = $app->getContainer();
        $settings = $container->get(SettingsInterface::class);

        $mailerClient = $container->get(Mailer::class);

        $personWithPostPersistData = $this->getInitialisedPerson(false);
        $requestBody = $personWithPostPersistData->toMailerPayload();

        $clientProphecy = $this->prophesize(Client::class);
        $clientProphecy->post(
            $settings->get('apiClient')['mailer']['baseUri'] . '/v1/send',
            [
                'json' => $requestBody,
                'headers' => [
                    'x-send-verify-hash' => '672132c155bca0f63211da07a70304a3c9eba4c57f4f4702bc220aa85ee04ac8',
                ],
            ]
        )->shouldBeCalledOnce()
        ->willThrow(RequestExceptionInterface::class);

        $container->set(Client::class, $clientProphecy->reveal());

        $sendSuccessful = $mailerClient->sendEmail($requestBody);

        // assert false
        $this->assertEquals(false, $sendSuccessful);
    }
}
