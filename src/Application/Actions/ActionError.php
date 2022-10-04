<?php

declare(strict_types=1);

namespace BigGive\Identity\Application\Actions;

use JsonSerializable;

class ActionError implements JsonSerializable
{
    public const BAD_REQUEST = 'BAD_REQUEST';
    public const INSUFFICIENT_PRIVILEGES = 'INSUFFICIENT_PRIVILEGES';
    public const NOT_ALLOWED = 'NOT_ALLOWED';
    public const NOT_IMPLEMENTED = 'NOT_IMPLEMENTED';
    public const RESOURCE_NOT_FOUND = 'RESOURCE_NOT_FOUND';
    public const SERVER_ERROR = 'SERVER_ERROR';
    public const UNAUTHENTICATED = 'UNAUTHENTICATED';
    public const VALIDATION_ERROR = 'VALIDATION_ERROR';
    public const VERIFICATION_ERROR = 'VERIFICATION_ERROR';

    private string $type;

    private string $description;

    public function __construct(string $type, ?string $description)
    {
        $this->type = $type;
        $this->description = $description;
    }

    #[\ReturnTypeWillChange]
    public function jsonSerialize(): array
    {
        $payload = [
            'type' => $this->type,
            'description' => $this->description,
        ];

        return $payload;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function setDescription(?string $description = null): self
    {
        $this->description = $description;
        return $this;
    }
}
