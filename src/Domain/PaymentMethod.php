<?php

declare(strict_types=1);

namespace BigGive\Identity\Domain;

use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;
use OpenApi\Annotations as OA;
use Symfony\Component\Uid\Uuid;

/**
 * @ORM\Entity()
 * @ORM\HasLifecycleCallbacks
 * @ORM\Table
 * @OA\Schema(
 *  description="Reusable payment method, e.g. a saved credit card. Full card numbers or wallet tokens
 *  are not stored by us, only a reference to the PSP's record.",
 * )
 */
class PaymentMethod implements JsonSerializable
{
    use TimestampsTrait;

    /**
     * @ORM\Id
     * @ORM\Column(type="uuid", unique=true)
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class="\Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator")
     */
    public Uuid $id;

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
     * @var string Unique identifier issued by the PSP for this PaymentMethod.
     * @todo migrate
     */
    public string $stripe_payment_method_id;

    /**
     * @ORM\Column(type="string", nullable=true)
     * @var string|null Issuer, e.g. 'visa'.
     */
    public ?string $card_brand = null;

    /**
     * @todo figure out how we want to map this from month + year
     * @var \DateTime|null
     */
    public ?string $card_expiry = null;

    /**
     * @ORM\Column(type="string", length=4, nullable=true)
     * @var string|null Stores last 4 digits of card number.
     * @todo decide whether to use virtual or real last 4 for wallets, or store both.
     */
    public ?string $card_last_four = null;

    /**
     * @ORM\Column(type="string", nullable=true)
     * @var string|null Wallet provider, e.g. 'google_pay'.
     */
    public ?string $card_wallet = null;

    /**
     * @ORM\Column(type="string", nullable=true)
     * @OA\Property()
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
