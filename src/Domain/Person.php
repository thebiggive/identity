<?php

declare(strict_types=1);

namespace BigGive\Identity\Domain;

use BigGive\Identity\Application\Security\Password;
use BigGive\Identity\Client\Mailer;
use BigGive\Identity\Repository\PersonRepository;
use Doctrine\ORM\Mapping as ORM;
use OpenApi\Annotations as OA;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\NotCompromisedPassword;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * @psalm-import-type RequestBody from Mailer as MailerRequestBody
 *
 * @psalm-suppress PossiblyUnusedProperty - properties are used in FE after this is serialised.
 *
 * @OA\Schema(
 *  description="Person – initially anonymous. To be login-ready, first_name,
 *  last_name, email_address and password are required.",
 * )
 * @see Credentials
 */
#[ORM\Table(name: 'Person')]
#[ORM\Index(name: 'email_and_password', columns: ['email_address', 'password'])]
#[ORM\Entity(repositoryClass: PersonRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Person
{
    use TimestampsTrait;

    public const int MIN_PASSWORD_LENGTH = 10;

    public const array SERIALISED_FOR_UPDATE_ATTRIBUTES = [
        'first_name',
        'last_name',
        'email_address',
        'home_address_line_1',
        'home_postcode',
        'home_country_code',
        'raw_password',
    ];

    /**
     * These properties should be excluded from serialisation, as the front-end does not use them.
     */
    public const array NON_SERIALISED_ATTRIBUTES = [
        'created_at',
        'updated_at',
        "captcha_code", // sent FROM frontend, doesn't ever need to be sent to frontend.
        "skipCaptchaCheck",
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
     * @OA\Property(
     *     type="object",
     *     description="Properties are lowercase currency codes, e.g. 'gbp'. Values are
     *     available amounts in smallest denomination, e.g. 123 pence.",
     *     example={
     *         "eur": 0,
     *         "gbp": 123,
     *     }
     * )
     * @var null|array<string,int>  Total Pending Payment Intents for Big Give
     *                  (i.e. donor fund top up tips) for each currency
     *                  in smallest unit (cents/pence), keyed on lowercase currency code.
     *                  Or may be null if no tip balances requested on Get.
     */
    public ?array $pending_tip_balance = null;

    /**
     * @OA\Property(
     *  property="id",
     *  format="uuid",
     *  example="f7095caf-7180-4ddf-a212-44bacde69066",
     *  pattern="[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}",
     * )
     */
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    private ?Uuid $id = null;

    /**
     * @OA\Property(
     *  property="first_name",
     *  description="The person's first name",
     *  example="Loraine",
     * )
     * @var string The person's first name.
     */
    #[Assert\NotBlank(groups: ['complete'])]
    #[ORM\Column(type: 'string', nullable: true)]
    public ?string $first_name = null;

    /**
     * @OA\Property(
     *  property="last_name",
     *  description="The person's surname",
     *  example="James",
     * )
     * @var string The person's last name / surname.
     */
    #[Assert\NotBlank(groups: ['complete'])]
    #[ORM\Column(type: 'string', nullable: true)]
    public ?string $last_name = null;

    /**
     * @OA\Property(
     *  property="email_address",
     *  format="email",
     *  example="loraine@example.org",
     * )
     * @var string The email address of the person. Email address must be unique amongst
     * password-enabled Person records.
     */
    #[ORM\Column(type: 'string', nullable: true)]
    #[Assert\NotBlank(groups: ['complete'])]
    public ?string $email_address = null;

    /**
     * @OA\Property()
     * @var string|null From residential address, if donor is claiming Gift Aid.
     */
    #[ORM\Column(type: 'string', nullable: true)]
    public ?string $home_address_line_1 = null;

    /**
     * @OA\Property()
     * @var string|null From residential address, if donor is claiming Gift Aid and is GB-resident.
     */
    #[ORM\Column(type: 'string', nullable: true)]
    public ?string $home_postcode = null;

    /**
     * @OA\Property()
     * @var string|null From residential address, if donor is claiming Gift Aid. Can be 'GB' or 'OVERSEAS',
     *                  or null if not applicable. Consuming code should assume that additional ISO 3166-1
     *                  alpha-2 country codes could be set in the future.
     */
    #[ORM\Column(type: 'string', nullable: true)]
    public ?string $home_country_code = null;

    /**
     * JSON Web Token that lets somebody set a password to make the account reusable.
     */
    public ?string $completion_jwt = null;

    /**
     * @OA\Property()
     * */
    public bool $has_password = false; // <--- confusingly this seems to never be written to

    /**
     * @var string|null Hashed password, if a password has been set.
     */
    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $password = null;

    private bool $skipCaptchaCheck = false;

    /**
     * @OA\Property(
     *  description="One-time code for a solved captcha; required on new registration. Write-only property, not sent
     * in responses.",
     *  type="string",
     *  example="some-token-123",
     * )
     * @var string|null Used only on create; not persisted.
     * @see Person::validateCaptchaExistsIfNew()
     */
    public ?string $captcha_code = null;

    /**
     * @OA\Property(
     *  property="raw_password",
     *  description="Plain text password; required to enable future logins",
     *  type="string",
     *  format="password",
     *  example="mySecurePassword123",
     * )
     * @var string|null Used on create; only hash of this is persisted.
     * @see Person::validateCaptchaExistsIfNew()
     */
    public ?string $raw_password = null;

    /**
     * @OA\Property()
     */
    #[ORM\Column(type: 'string', nullable: true)]
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

    /**
     * @psalm-return MailerRequestBody
     */
    public function toMailerPayload(): array
    {
        if ($this->email_address === null) {
            throw new \RuntimeException(
                "Email address is null for {$this->id?->__tostring()}, cannot make mailer payload"
            );
        }

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
     * @see Person::$captcha_code
     *
     * @psalm-suppress PossiblyUnusedMethod - used via callback.
     */
    #[Assert\Callback(groups: ['new'])]
    public function validateCaptchaExistsIfNew(ExecutionContextInterface $context): void
    {
        // Brand new entity + no captcha solved.
        if (!$this->skipCaptchaCheck && empty($this->id) && empty($this->captcha_code)) {
            $context->buildViolation('Captcha is required to create an account')
                ->atPath('captcha_code')
                ->addViolation();
        }
    }

    /**
     * @see Person::$raw_password
     *
     * Checks for whether password was compromised using the API of https://haveibeenpwned.com/Passwords
     *
     * @psalm-suppress PossiblyUnusedMethod - used via callback.
     */
    #[Assert\Callback(groups: ["complete"])]
    public function validatePasswordIfNotBlank(ExecutionContextInterface $context): void
    {
        $passwordUpdated = $this->raw_password !== null && $this->raw_password !== '';

        if (! $passwordUpdated) {
            return;
        }
        if (mb_strlen($this->raw_password) < static::MIN_PASSWORD_LENGTH) {
            $context->buildViolation(sprintf(
                'Your password could not be set. Please ensure you chose one with at least %d characters.',
                static::MIN_PASSWORD_LENGTH
            ))
                ->atPath('raw_password')
                ->addViolation();

            return;
        }

        // passing the optional http client param just so its clear we will connect to HTTP here.
        $httpClient = HttpClient::create();
        $notCompromisedValidator = new Assert\NotCompromisedPasswordValidator($httpClient);
        $notCompromisedValidator->initialize($context);

        $notCompromisedValidator->validate($this->raw_password, new NotCompromisedPassword(skipOnError: true));
    }

    public function addCompletionJWT(string $completionJWT): void
    {
        $this->completion_jwt = $completionJWT;
    }

    public function skipCaptchaPresenceValidation(): void
    {
        $this->skipCaptchaCheck = true;
    }
}
