<?php

declare(strict_types=1);

namespace BigGive\Identity\Tests\Application\Actions;

use BigGive\Identity\Application\Actions\ActionPayload;
use BigGive\Identity\Tests\TestCase;

class StatusTest extends TestCase
{
    public function testSuccess(): void
    {
        $app = $this->getAppInstance();

        $request = $this->createRequest('GET', '/ping');
        $response = $app->handle($request);
        $payload = (string) $response->getBody();

        $expectedPayload = new ActionPayload(200, ['status' => 'OK']);
        $expectedSerialised = json_encode($expectedPayload, JSON_PRETTY_PRINT);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($expectedSerialised, $payload);
    }
}
