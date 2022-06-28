<?php

declare(strict_types=1);

namespace BigGive\Identity\Domain;

use Doctrine\ORM\Mapping as ORM;
use JsonException;
use JsonSerializable;

/**
 * @ORM\Entity()
 * @ORM\HasLifecycleCallbacks
 * @ORM\Table
 */
class PaymentMethod implements JsonSerializable
{
    use TimestampsTrait;

    /**
     * @ORM\Id
     * @ORM\Column(type="integer", options={"unsigned": true})
     * @ORM\GeneratedValue(strategy="AUTO")
     * @var int|null
     */
    public ?int $id = null;

    /**
     * @ORM\ManyToOne(targetEntity="Person", inversedBy="paymentMethods", cascade={"persist"})
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
    public ?string $billingFirstAddressLine = null;

    /**
     * @ORM\Column(type="string", nullable=true)
     * @var string|null Stores billing post code, nullable.
     */
    public ?string $billingPostcode = null;

    /**
     * @ORM\Column(type="string", nullable=true)
     * @var string|null stores the country the card used is registered in, nullable.
     */
    public ?string $billingCountryCode = null;

    public function jsonSerialize(): mixed
    {
        try {
            return json_encode([
                'user_id' => $this->user->getId(),
                ...get_object_vars($this)
            ], JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            // todo
        }
    }
}
