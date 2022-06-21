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
     * @ORM\ManyToOne(targetEntity="Person", cascade={"persist"})
     * @var Person
     */
    protected Person $user;

    public string $psp = 'stripe';

    public string $token;

    public ?string $billingFirstAddressLine = null;

    public ?string $billingPostcode = null;

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
