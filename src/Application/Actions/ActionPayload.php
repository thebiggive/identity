<?php

declare(strict_types=1);

namespace BigGive\Identity\Application\Actions;

use JsonSerializable;

class ActionPayload implements JsonSerializable
{
    public function __construct(
        private int $statusCode = 200,
        private array | object | null $data = null,
        private ?ActionError $error = null
    ) {
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function jsonSerialize(): object | array | null
    {
        $payload = null;

        if ($this->data !== null) {
            $payload = $this->data;
        } elseif ($this->error !== null) {
            $payload = ['error' => $this->error];
        }

        return $payload;
    }
}
