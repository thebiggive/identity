<?php

declare(strict_types=1);

namespace BigGive\Identity\Domain;

use BigGive\Identity\Application\Security\Password;
use Doctrine\ORM\Mapping as ORM;
use OpenApi\Annotations as OA;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * @ORM\Entity(repositoryClass="BigGive\Identity\Repository\PersonRepository")
 * @ORM\HasLifecycleCallbacks
 * @ORM\Table
 * @OA\Schema(
 *  description="Person – initially anonymous. To be login-ready, first_name,
 *  last_name, email_address and password are required.",
 * )
 * @see Credentials
 */
class Person
{
    use TimestampsTrait;

    public const MIN_PASSWORD_LENGTH = 10;

    public const NON_SERIALISED_FOR_UPDATE_ATTRIBUTES = [
        'id',
        'created_at',
        'updated_at',
    ];

    /**
     * Keeping this placeholder for now (used in 3 places) for convenience if we do decide to
     * exclude public properties, though this is less certain now we're using the Symfony serializer
     * more appropriately. Keep until we pick up ID-19 and either populate or delete use at that point.
     * @var string[]
     */
    public const NON_SERIALISED_ATTRIBUTES = [
    ];

    /**
     * @OA\Property(
     *     type="object",
     *     description="Properties are lowercase currency codes, e.g. 'gbp'. Values are
     *     available amounts in smallest denomination, e.g. 123 pence.",
     *     example={
     *         "eur": 0,
     *         "gbp": 123,
     *     }
     * )
     * @var int[] Balances in smallest unit (cents/pence) keyed on lowercase currency code.
     */
    public array $cash_balance = [];

    /**
     * @ORM\Id
     * @ORM\Column(type="uuid", unique=true)
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class="\Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator")
     * @OA\Property(
     *  property="id",
     *  format="uuid",
     *  example="f7095caf-7180-4ddf-a212-44bacde69066",
     *  pattern="[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}",
     * )
     */
    private ?Uuid $id = null;

    /**
     * @ORM\Column(type="string", nullable=true)
     * @Assert\NotBlank(groups={"complete"})
     * @OA\Property(
     *  property="first_name",
     *  description="The person's first name",
     *  example="Loraine",
     * )
     * @var string The person's first name.
     */
    public ?string $first_name = null;

    /**
     * @ORM\Column(type="string", nullable=true)
     * @Assert\NotBlank(groups={"complete"})
     * @OA\Property(
     *  property="last_name",
     *  description="The person's surname",
     *  example="James",
     * )
     * @var string The person's last name / surname.
     */
    public ?string $last_name = null;

    /**
     * @ORM\Column(type="string", unique=true, nullable=true)
     * @Assert\NotBlank(groups={"complete"})
     * @OA\Property(
     *  property="email_address",
     *  format="email",
     *  example="loraine@example.org",
     * )
     * @var string The email address of the person. Email address must be unique.
     */
    public ?string $email_address = null;

    /**
     * @ORM\Column(type="string", nullable=true)
     * @OA\Property()
     * @var string|null From residential address, if donor is claiming Gift Aid.
     */
    public ?string $home_address_line_1 = null;

    /**
     * @ORM\Column(type="string", nullable=true)
     * @OA\Property()
     * @var string|null From residential address, if donor is claiming Gift Aid and is GB-resident.
     */
    public ?string $home_postcode = null;

    /**
     * @ORM\Column(type="string", nullable=true)
     * @OA\Property()
     * @var string|null From residential address, if donor is claiming Gift Aid. Can be 'GB' or 'OVERSEAS',
     *                  or null if not applicable. Consuming code should assume that additional ISO 3166-1
     *                  alpha-2 country codes could be set in the future.
     */
    public ?string $home_country_code = null;

    /**
     * JSON Web Token that lets somebody set a password to make the account reusable.
     */
    public ?string $completion_jwt = null;

    /**
     * @OA\Property()
     */
    public bool $has_password = false;

    /**
     * @ORM\Column(type="string", nullable=true)
     * @var string|null Hashed password, if a password has been set.
     */
    private ?string $password = null;

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

    /**
     * @ORM\Column(type="string", nullable=true)
     * @OA\Property()
     */
    public ?string $stripe_customer_id = null;

    public function __construct()
    {
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function setId(?Uuid $id): void
    {
        $this->id = $id;
    }

    public function getFirstName(): ?string
    {
        return $this->first_name;
    }

    public function getLastName(): ?string
    {
        return $this->last_name;
    }

    public function hashPassword(): void
    {
        if (!empty($this->raw_password)) {
            $this->password = Password::hash($this->raw_password);
        }
    }

    public function getPasswordHash(): ?string
    {
        return $this->password;
    }

    public function setStripeCustomerId(?string $stripe_customer_id): void
    {
        $this->stripe_customer_id = $stripe_customer_id;
    }

    public function toMailerPayload(): array
    {
        $data = [
            'templateKey' => 'donor-registered',
            'recipientEmailAddress' => $this->email_address,
            'forGlobalCampaign' => false,
            'params' => [
                'donorFirstName' => $this->getFirstName(),
                'donorEmail' => $this->email_address,
            ],
        ];

        return $data;
    }

    /**
     * @Assert\Callback(groups={"new"})
     * @see Person::$captcha_code
     */
    public function validateCaptchaExistsIfNew(ExecutionContextInterface $context): void
    {
        // Brand new entity + no captcha solved.
        if (empty($this->id) && empty($this->captcha_code)) {
            $context->buildViolation('Captcha is required to create an account')
                ->atPath('captcha_code')
                ->addViolation();
        }
    }

    /**
     * @Assert\Callback(groups={"complete"})
     * @see Person::$raw_password
     */
    public function validatePasswordIfNotBlank(ExecutionContextInterface $context): void
    {
        $passwordUpdatedAndTooShort = (
            !empty($this->raw_password) &&
            mb_strlen($this->raw_password) < static::MIN_PASSWORD_LENGTH
        );
        if ($passwordUpdatedAndTooShort) {
            $context->buildViolation(sprintf(
                'Password must be %d or more characters',
                static::MIN_PASSWORD_LENGTH
            ))
                ->atPath('raw_password')
                ->addViolation();
        }
    }

    public function addCompletionJWT(string $completionJWT): void
    {
        $this->completion_jwt = $completionJWT;
    }
}
