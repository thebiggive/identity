<?php

declare(strict_types=1);

namespace BigGive\Identity\Tests\Application\Actions;

use BigGive\Identity\Tests\TestCase;
use Psr\Http\Message\ServerRequestInterface;

class CreatePersonTest extends TestCase
{
    public function testSuccess(): void
    {
        $app = $this->getAppInstance();

        $request = $this->buildRequest([
            'first_name' => 'Loraine',
            'last_name' => 'James',
            'raw_password' => 'superSecure123',
            'email_address' => 'loraine@hyperdub.net',
            'captcha_code' => 'good response',
        ]);

        $response = $app->handle($request);
        $payloadJSON = (string) $response->getBody();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertJson($payloadJSON);

        $payload = json_decode($payloadJSON);

        $this->assertJson($payload->uuid);
        $this->assertSame(32, strlen($payload->getData()->uuid));
        $this->assertEquals('Loraine', $payload->first_name);
        $this->assertNotEmpty($payload->created_at);
        $this->assertNotEmpty($payload->updated_at);
    }

    public function testFailingCaptcha(): void
    {
        $app = $this->getAppInstance();

        $request = $this->buildRequest([
            'first_name' => 'Loraine',
            'last_name' => 'James',
            'raw_password' => 'superSecure123',
            'email_address' => 'loraine@hyperdub.net',
            'captcha_code' => 'bad response',
        ]);

        $response = $app->handle($request);
        $payloadJSON = (string) $response->getBody();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertJson($payloadJSON);

        $payload = json_decode($payloadJSON);

        $this->assertJson($payload->uuid);
        $this->assertSame(32, strlen($payload->getData()->uuid));
        $this->assertEquals('Loraine', $payload->first_name);
        $this->assertNotEmpty($payload->created_at);
        $this->assertNotEmpty($payload->updated_at);
    }

    public function testMissingCaptcha(): void
    {
        $app = $this->getAppInstance();

        $request = $this->buildRequest([
            'first_name' => 'Loraine',
            'last_name' => 'James',
            'raw_password' => 'superSecure123',
            'email_address' => 'loraine@hyperdub.net',
        ]);

        $response = $app->handle($request);
        $payloadJSON = (string) $response->getBody();

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertJson($payloadJSON);

        $payload = json_decode($payloadJSON);

        $this->assertJson($payload->uuid);
        $this->assertSame(32, strlen($payload->getData()->uuid));
        $this->assertEquals('Loraine', $payload->first_name);
        $this->assertNotEmpty($payload->created_at);
        $this->assertNotEmpty($payload->updated_at);
    }

    public function testMissingData(): void
    {
        $app = $this->getAppInstance();

        $request = $this->buildRequest([
            'first_name' => 'Loraine',
            'captcha_code' => 'good response',
        ]);

        $response = $app->handle($request);
        $payloadJSON = (string) $response->getBody();

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertJson($payloadJSON);

        $payload = json_decode($payloadJSON);
        $expectedJSON = json_encode([
            'errors' => [
                'Missing last_name',
            ]
        ], JSON_THROW_ON_ERROR);
        $this->assertJsonStringEqualsJsonString($expectedJSON, $payload);
    }

    private function buildRequest(array $payloadValues): ServerRequestInterface
    {
        return $this->buildRequestRaw(json_encode($payloadValues, JSON_THROW_ON_ERROR));
    }

    private function buildRequestRaw(string $payloadLiteral): ServerRequestInterface
    {
        // Accept JSON is the `createRequest()` default.
        $request = $this->createRequest(
            'POST',
            '/v1/people',
            [
                'HTTP_ACCEPT' => 'application/json',
                // Simulate ALB in unit tests by default. Rate limit middleware needs an IP from somewhere to not crash.
                'HTTP_X-Forwarded-For' => '1.2.3.4',
            ],
        );
        $request->getBody()->write($payloadLiteral);

        return $request;
    }
}
