<?php

declare(strict_types=1);

namespace BigGive\Identity\Domain;

use JsonSerializable;

/**
 * @todo ORM map everything!
 */
class Person implements JsonSerializable
{
    public ?int $id = null; // TODO decide between numeric and UUIDs as primary.

    public string $emailAddress;

    private string $password;

    private ?string $stripeCustomerId = null;

    public string $firstName;

    public string $lastName;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    #[\ReturnTypeWillChange]
    public function jsonSerialize(): array
    {
        return json_encode(get_object_vars($this), JSON_THROW_ON_ERROR);
    }
}
