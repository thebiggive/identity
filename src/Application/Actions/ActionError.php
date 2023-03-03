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

    public const DUPLICATE_EMAIL_ADDRESS_WITH_PASSWORD = 'DUPLICATE_EMAIL_ADDRESS_WITH_PASSWORD';

    public function __construct(private string $type, private string $description, private ?array $trace = null, private ?string $htmlDescription = null)
    {
    }

    public function jsonSerialize(): mixed
    {
        $payload = [
            'type' => $this->type,
            'description' => $this->description,
        ];

        if ($this->trace !== null) {
            $payload['trace'] = $this->trace;
        }

        if ($this->htmlDescription !== null) {
            $payload['htmlDescription'] = $this->htmlDescription;
        }

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
