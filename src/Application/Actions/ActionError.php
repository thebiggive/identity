<?php

declare(strict_types=1);

namespace BigGive\Identity\Application\Actions;

use JsonSerializable;

class ActionError implements JsonSerializable
{
    public const string BAD_REQUEST = 'BAD_REQUEST';
    public const string INSUFFICIENT_PRIVILEGES = 'INSUFFICIENT_PRIVILEGES';
    public const string NOT_ALLOWED = 'NOT_ALLOWED';
    public const string NOT_IMPLEMENTED = 'NOT_IMPLEMENTED';
    public const string RESOURCE_NOT_FOUND = 'RESOURCE_NOT_FOUND';
    public const string SERVER_ERROR = 'SERVER_ERROR';
    public const string UNAUTHENTICATED = 'UNAUTHENTICATED';
    public const string VALIDATION_ERROR = 'VALIDATION_ERROR';
    public const string VERIFICATION_ERROR = 'VERIFICATION_ERROR';

    public const string DUPLICATE_EMAIL_ADDRESS_WITH_PASSWORD = 'DUPLICATE_EMAIL_ADDRESS_WITH_PASSWORD';

    public function __construct(
        private string $type,
        private string $description,
        private ?array $trace = null,
        private ?string $htmlDescription = null,
    ) {
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
