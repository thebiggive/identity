<?php

declare(strict_types=1);

namespace BigGive\Identity\Domain;

use BigGive\Identity\Application\Security\Password;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

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
    public Collection | array $payment_methods = [];

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
     * @ORM\Column(type="string")
     * @Assert\NotBlank()
     * @var string The person's first name.
     */
    public string $first_name;

    /**
     * @ORM\Column(type="string")
     * @Assert\NotBlank()
     * @var string The person's last name / surname.
     */
    public string $last_name;

    /**
     * @ORM\Column(type="string", unique=true)
     * @Assert\NotBlank()
     * @var string The email address of the person. Email address must be unique.
     */
    public string $email_address;

    private string $password;

    /**
     * @var string|null Used only on create; not persisted.
     * @see Person::validateCaptchaAndRawPasswordSetIfNew()
     */
    public ?string $recaptcha_code = null;

    /**
     * @var string|null Used on create; only hash of this is persisted.
     * @see Person::validateCaptchaAndRawPasswordSetIfNew()
     */
    public ?string $raw_password = null;

    private ?string $stripe_customer_id = null;

    public function __construct()
    {
        $this->payment_methods = new ArrayCollection();
    }

    public function getId(): UuidInterface
    {
        return $this->id;
    }

    public function getFirstName(): string
    {
        return $this->first_name;
    }

    public function getLastName(): string
    {
        return $this->last_name;
    }

    public function hashPassword(): void
    {
        $this->password = Password::hash($this->raw_password);
    }

    public function getPasswordHash(): string
    {
        return $this->password;
    }

    #[\ReturnTypeWillChange]
    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }

    /**
     * @Assert\Callback
     * @see Person::$recaptcha_code
     * @see Person::$raw_password
     */
    public function validateCaptchaAndRawPasswordSetIfNew(ExecutionContextInterface $context, $payload): void
    {
        // Brand new entity + no captcha solved.
        if (empty($this->id) && empty($payload['recaptcha_code'])) {
            $context->buildViolation('Captcha is required to create an account')
                ->atPath('recaptcha_code')
                ->addViolation();
        }

        // Entity brand new or somehow otherwise without a password, and none set.
        if (empty($this->password) && empty($payload['raw_password'])) {
            $context->buildViolation('Password is required to create an account')
                ->atPath('raw_password')
                ->addViolation();
        }
    }
}
