<?php

declare(strict_types=1);

namespace BigGive\Identity\Domain;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;

/**
 * @ORM\Entity()
 * @ORM\HasLifecycleCallbacks
 * @ORM\Table
 */
class Person implements JsonSerializable
{
    use TimestampsTrait;

    /**
     * @ORM\OneToMany(targetEntity="PaymentMethod", mappedBy="person", fetch="EAGER")
     * @var Collection|PaymentMethod[]
     */
    public Collection | array $paymentMethods;

    // TODO decide between numeric and UUIDs as primary/ whether UUIDs worth it at all.
    // Enable Ramsey type if so, tidy up UUID refs if not.
    /**
     * @ORM\Id
     * @ORM\Column(type="integer", options={"unsigned": true})
     * @ORM\GeneratedValue(strategy="AUTO")
     * @var int|null
     */
    public ?int $id = null;

    public string $emailAddress;

    private string $password;

    private ?string $stripeCustomerId = null;

    public string $firstName;

    public string $lastName;

    public function __construct()
    {
        $this->paymentMethods = new ArrayCollection();
    }

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
