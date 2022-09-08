<?php

declare(strict_types=1);

namespace BigGive\Identity\Domain;

use BigGive\Identity\Application\Security\Password;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;
use OpenApi\Annotations as OA;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * @ORM\Entity(repositoryClass="BigGive\Identity\Repository\PersonRepository")
 * @ORM\HasLifecycleCallbacks
 * @ORM\Table
 * @OA\Schema(
 *  required={"first_name", "last_name", "email_address"},
 * )
 * @see Credentials
 */
class Person implements JsonSerializable
{
    use TimestampsTrait;

    public const MIN_PASSWORD_LENGTH = 10;

    /**
     * @ORM\OneToMany(targetEntity="PaymentMethod", mappedBy="person", fetch="EAGER")
     * @var Collection|PaymentMethod[]
     */
    public Collection | array $payment_methods = [];

    /**
     * @ORM\Id
     * @ORM\Column(type="uuid_binary_ordered_time", unique=true)
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class="Ramsey\Uuid\Doctrine\UuidOrderedTimeGenerator")
     * @OA\Property(
     *  property="id",
     *  format="uuid",
     *  example="f7095caf-7180-4ddf-a212-44bacde69066",
     *  pattern="[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}",
     * )
     */
    public ?UuidInterface $id = null;

    /**
     * @ORM\Column(type="string")
     * @Assert\NotBlank()
     * @OA\Property(
     *  property="first_name",
     *  description="The person's first name",
     *  example="Loraine",
     * )
     * @var string The person's first name.
     */
    public string $first_name;

    /**
     * @ORM\Column(type="string")
     * @Assert\NotBlank()
     * @OA\Property(
     *  property="last_name",
     *  description="The person's surname",
     *  example="James",
     * )
     * @var string The person's last name / surname.
     */
    public string $last_name;

    /**
     * @ORM\Column(type="string", unique=true)
     * @Assert\NotBlank()
     * @OA\Property(
     *  property="email_address",
     *  format="email",
     *  example="loraine@example.org",
     * )
     * @var string The email address of the person. Email address must be unique.
     */
    public string $email_address;

    private string $password;

    /**
     * @OA\Property(
     *  description="One-time code for a solved captcha; required on new registration",
     *  type="string",
     *  example="some-token-123",
     * )
     * @var string|null Used only on create; not persisted.
     * @see Person::validateCaptchaAndRawPasswordSetIfNew()
     */
    public ?string $captcha_code = null;

    /**
     * @OA\Property(
     *  property="raw_password",
     *  description="Plain text password; required on new registration",
     *  type="string",
     *  format="password",
     *  example="mySecurePassword123",
     * )
     * @var string|null Used on create; only hash of this is persisted.
     * @see Person::validateCaptchaAndRawPasswordSetIfNew()
     */
    public ?string $raw_password = null;

    private ?string $stripe_customer_id = null;

    public function __construct()
    {
        $this->payment_methods = new ArrayCollection();
    }

    public function getId(): ?UuidInterface
    {
        return $this->id;
    }

    public function setId(?UuidInterface $id): void
    {
        $this->id = $id;
    }

    public function getFirstName(): string
    {
        return $this->first_name;
    }

    public function getLastName(): string
    {
        return $this->last_name;
    }

    public function getEmailAddress(): string
    {
        return $this->email_address;
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
        $jsonVars = get_object_vars($this);
        $jsonVars['uuid'] = $this->getId()?->toString();
        return $jsonVars;
    }

    public function toMailerPayload(): array
    {
        $data = [
            'templateKey' => 'donor-registered',
            'recipientEmailAddress' => $this->getEmailAddress(),
            'forGlobalCampaign' => false,
            'params' => [
                'donorFirstName' => $this->getFirstName(),
                'donorEmail' => $this->getEmailAddress(),
            ],
        ];

        return $data;
    }

    /**
     * @Assert\Callback()
     * @see Person::$captcha_code
     * @see Person::$raw_password
     */
    public function validateCaptchaAndRawPasswordSetIfNew(ExecutionContextInterface $context): void
    {
        // Brand new entity + no captcha solved.
        if (empty($this->id) && empty($this->captcha_code)) {
            $context->buildViolation('Captcha is required to create an account')
                ->atPath('captcha_code')
                ->addViolation();
        }

        // Entity brand new or somehow otherwise without a password, and none set.
        $passwordMissingOrInvalid = (
            empty($this->password) &&
            (empty($this->raw_password) || mb_strlen($this->raw_password) < static::MIN_PASSWORD_LENGTH)
        );
        if ($passwordMissingOrInvalid) {
            $context->buildViolation(sprintf(
                'Password of %d or more characters is required to create an account',
                static::MIN_PASSWORD_LENGTH
            ))
                ->atPath('raw_password')
                ->addViolation();
        }
    }
}
