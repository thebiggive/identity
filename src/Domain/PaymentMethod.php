<?php

declare(strict_types=1);

namespace BigGive\Identity\Domain;

use Doctrine\ORM\Mapping as ORM;
use JetBrains\PhpStorm\ArrayShape;
use JsonException;
use JsonSerializable;
use Ramsey\Uuid\UuidInterface;

/**
 * @ORM\Entity()
 * @ORM\HasLifecycleCallbacks
 * @ORM\Table
 */
class PaymentMethod implements JsonSerializable
{
    use TimestampsTrait;

    /**
     * @var \Ramsey\Uuid\UuidInterface
     *
     * @ORM\Id
     * @ORM\Column(type="uuid_binary_ordered_time", unique=true)
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class="Ramsey\Uuid\Doctrine\UuidOrderedTimeGenerator")
     */
    public UuidInterface $id;

    /**
     * @ORM\ManyToOne(targetEntity="Person", inversedBy="payment_methods", cascade={"persist"})
     */
    protected Person $person;

    /**
     * @ORM\Column(type="string")
     * @var string Stores what payment service provider is used - currently Stripe for everyone.
     */
    public string $psp = 'stripe';

    /**
     * @ORM\Column(type="string", unique=true)
     * @var string Unique token to identify a specific PaymentMethod record.
     */
    public string $token;

    /**
     * @ORM\Column(type="string", nullable=true)
     * @var string|null Stores first line of billing adress, nullable.
     */
    public ?string $billing_first_address_line = null;

    /**
     * @ORM\Column(type="string", nullable=true)
     * @var string|null Stores billing post code, nullable.
     */
    public ?string $billing_postcode = null;

    /**
     * @ORM\Column(type="string", nullable=true)
     * @var string|null stores the country the card used is registered in, nullable.
     */
    public ?string $billing_country_code = null;

    public function setPerson(Person $person): void
    {
        $this->person = $person;
    }

    public function getPerson(): Person
    {
        return $this->person;
    }

    #[\ReturnTypeWillChange]
    public function jsonSerialize(): array
    {
        return [
            'person_id' => $this->person->getId(),
            ...get_object_vars($this)
        ];
    }
}
