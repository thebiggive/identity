<?php

declare(strict_types=1);

namespace BigGive\Identity\Domain;

use Assert\Assertion;
use BigGive\Identity\Application\Security\Password;
use BigGive\Identity\Client\Mailer;
use BigGive\Identity\Domain\Normalizers\HasPasswordNormalizer;
use BigGive\Identity\Repository\PersonRepository;
use Doctrine\ORM\Mapping as ORM;
use OpenApi\Annotations as OA;
use Ramsey\Uuid\Rfc4122\UuidV4;
use Random\Randomizer;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\NotCompromisedPassword;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * // phpcs:disable -- PHPCS disabled because afaik there's no way to write descriptions for OA properties without
 *                     something like making long lines as here or putting stars in the descriptions
 *
 * @psalm-import-type RequestBody from Mailer as MailerRequestBody
 *
 * @psalm-suppress PossiblyUnusedProperty - properties are used in FE after this is serialised.
 *
 * @OA\Schema(
 *  description="Person – initially anonymous. To be login-ready, first_name,
 *  last_name, email_address and password are required.",
 *
 *   @OA\Property(
 *   property="secretNumber",
 *   description="Secret six digit number required when creating the user with a password or setting password for first time to prove access to email",
 *   type="string",
 *   nullable=true,
 *   ),
 *
 *   @OA\Property(
 *   property="has_password",
 *   description="Whether or not the person has ever set a password. Person records without passwords set are auto-deleted unless a paassword is set shortly after",
 *   type="bool",
 *   ),
 * )
 * // phpcs:enable
 * @see Credentials
 */
#[ORM\Table(name: 'Person')]
#[ORM\Index(name: 'email_and_password', columns: ['email_address', 'password'])]
#[ORM\Entity(repositoryClass: PersonRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Person
{
    use TimestampsTrait;

    public const int MIN_PASSWORD_LENGTH = 12;

    public const array SERIALISED_FOR_UPDATE_ATTRIBUTES = [
        'first_name',
        'last_name',
        'email_address',
        'home_address_line_1',
        'home_postcode',
        'home_country_code',
    ];

    /**
     * These properties should be excluded from serialisation, as the front-end does not use them.
     */
    public const array NON_SERIALISED_ATTRIBUTES = [
        'notCompromisedPasswordValidator',
        'email_address_verified',
        'created_at',
        'updated_at',
        "captcha_code", // sent FROM frontend, doesn't ever need to be sent to frontend.
        "skipCaptchaCheck",
        "raw_password", // special rules around password handling, so set the property manually when required.
    ];

    /**
     * Long numbers are almost certainly mistakes, could be sensitive e.g. payment card no,
     * even if spaces between digits. Same regex used in \MatchBot\Domain\DonorName
     */
    public const string SIX_DIGITS_REGEX = '/\d\s?\d\s?\d\s?\d\s?\d\s?\d/';

    /**
     * Validation for 'complete' records, i.e. with password set.
     */
    public const string VALIDATION_COMPLETE = 'complete';

    /**
     * Validation for new records which can start off essentially empty if created in advance of a donation.
     */
    public const string VALIDATION_NEW = 'new';

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
     *     type="object",
     *     description="Properties are lowercase currency codes, e.g. 'gbp'. Values are
     *     available amounts in smallest denomination, e.g. 123 pence.",
     *     example={
     *         "eur": 0,
     *         "gbp": 123,
     *     }
     * )
     * @var null|array<string,int>  Total Succeeded Payment Intents for Big Give
     *                  (i.e. donor fund top up tips), created in past 10 days, for each currency
     *                  in smallest unit (cents/pence), keyed on lowercase currency code.
     *                  Or may be null if no tip balances requested on Get.
     */
    public ?array $recently_confirmed_tips_total = null;

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
    #[Assert\NotBlank(groups: [self::VALIDATION_COMPLETE])]
    #[Assert\Regex(
        pattern: self::SIX_DIGITS_REGEX,
        match: false,
        groups: [self::VALIDATION_COMPLETE, self::VALIDATION_NEW]
    )
    ]
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
    #[Assert\NotBlank(groups: [self::VALIDATION_COMPLETE])]
    #[ORM\Column(type: 'string', nullable: true)]
    #[Assert\Regex(
        pattern: self::SIX_DIGITS_REGEX,
        match: false,
        groups: [self::VALIDATION_COMPLETE, self::VALIDATION_NEW]
    )
    ]
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
    #[Assert\NotBlank(groups: [self::VALIDATION_COMPLETE])]
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
     * JSON Web Token that lets somebody update account details (name, & email) during donation.
     */
    public ?string $completion_jwt = null;

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
     * Always ensure the user has proved they have access to their email address (
     * and set {@see self::$email_address_verified} to show you've done that) when setting a password here or allowing
     * a deserialized Person with a password to be persisted
     *
     * @OA\Property(
     *  property="raw_password",
     *  description="Plain text password; required to enable future logins. Only pass string for specific
     *  password-setting operations, any value passed will be ignored in other cases. Never sent in responses.",
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


    /**
     * Has the person who set the password proved that they have read-access to the
     * {@see $email_address} given?
     *
     * Historically we didn't require this, but will for new accounts in future and may create
     * an optional verification process that holders of old accounts can use.
     */
    #[ORM\Column(nullable: true, type: 'datetime_immutable')]
    public ?\DateTimeImmutable $email_address_verified = null;

    /** Always null in prod for now as can't be saved in DB, set to double in tests. */
    private ?Assert\NotCompromisedPasswordValidator $notCompromisedPasswordValidator = null;

    public function __construct(?Assert\NotCompromisedPasswordValidator $notCompromisedPasswordValidator = null)
    {
        $this->notCompromisedPasswordValidator = $notCompromisedPasswordValidator;
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
     * Message with properties to sync via queue to MatchBot on password set or details update. Only
     * people with passwords are pushed this way.
     */
    public function toMatchBotSummaryMessage(): \Messages\Person
    {
        \assert($this->id !== null, 'Person ID must be set to sync to MatchBot');
        \assert($this->first_name !== null, 'First name must be set to sync to MatchBot');
        \assert($this->last_name !== null, 'Last name must be set to sync to MatchBot');
        \assert($this->email_address !== null, 'Email address must be set to sync to MatchBot');
        \assert($this->stripe_customer_id !== null, 'Stripe customer ID must be set to sync to MatchBot');

        $message = new \Messages\Person();
        $message->id = UuidV4::fromString($this->id->toRfc4122());
        $message->first_name = $this->first_name;
        $message->last_name = $this->last_name;
        $message->email_address = $this->email_address;
        $message->stripe_customer_id = $this->stripe_customer_id;
        $message->home_address_line_1 = $this->home_address_line_1;
        $message->home_postcode = $this->home_postcode;
        $message->home_country_code = $this->home_country_code;

        return $message;
    }

    /**
     * @see Person::$captcha_code
     *
     * @psalm-suppress PossiblyUnusedMethod - used via callback.
     */
    #[Assert\Callback(groups: [self::VALIDATION_NEW])]
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
    #[Assert\Callback(groups: [self::VALIDATION_COMPLETE])]
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

        $notCompromisedPasswordValidator =
            $this->notCompromisedPasswordValidator ?? $this->makeNotCompromisedValidator();

        $notCompromisedPasswordValidator->initialize($context);

        $notCompromisedPasswordValidator->validate($this->raw_password, new NotCompromisedPassword(skipOnError: true));
    }

    public function addCompletionJWT(string $completionJWT): void
    {
        $this->completion_jwt = $completionJWT;
    }

    public function skipCaptchaPresenceValidation(): void
    {
        $this->skipCaptchaCheck = true;
    }

    public function makeNotCompromisedValidator(): Assert\NotCompromisedPasswordValidator
    {
        $httpClient = HttpClient::create();
        $notCompromisedValidator = new Assert\NotCompromisedPasswordValidator($httpClient);
        return $notCompromisedValidator;
    }

    /**
     *
     * @psalm-suppress InvalidReturnType - working around new strictly typed stripe library that does not use
     * either classes or type aliases.
     * @psalm-suppress InvalidReturnStatement
     *
     * @return array{
     *     address?: array{
     *      city?: string, country?: string, line1?: string, line2?: string, postal_code?: string, state?: string
     *     }|null,
     *     balance?: int, cash_balance?: array{settings?: array{reconciliation_mode?: string}},
     *     description?: string, email?: string, expand?: array<array-key, string>, invoice_prefix?: string,
     *     invoice_settings?: array{custom_fields?: array<array-key, array{name: string, value: string}>|null,
     *     default_payment_method?: string, footer?: string,
     *     rendering_options?: array{amount_tax_display?: null|string, template?: string}|null},
     *     metadata?: \Stripe\StripeObject|null, name?: string, next_invoice_sequence?:
     *     int, payment_method?: string, phone?: string, preferred_locales?: array<array-key, string>,
     *     shipping?: array{address: array{city?: string, country?: string, line1?: string, line2?: string,
     *     postal_code?: string, state?: string}, name: string, phone?: string}|null, source?: string,
     *     tax?: array{ip_address?: null|string, validate_location?: string}, tax_exempt?: null|string,
     *     tax_id_data?: array<array-key, array{type: string, value: string}>,
     *     test_clock?: string, validate?: bool
     * }
     * @throws \Assert\AssertionFailedException
     */
    public function getStripeCustomerParams(): array
    {
        Assertion::eq(
            $this->first_name === null,
            $this->last_name === null,
            'Names are always both or neither set.'
        );

        $nameSet = $this->first_name !== null && $this->last_name !== null;
        $hasPasswordSince = $this->email_address_verified;

        $metadata = [
            'environment' => getenv('APP_ENV'),
            'personId' => (string) $this->getId(),
            ...($hasPasswordSince === null ? [] : [
                'hasPasswordSince' => $hasPasswordSince->format('Y-m-d H:i:s'),
                'emailAddress' => $this->email_address,
            ])
        ];

        $params = [
            'email' => $this->email_address,
            ...($nameSet ? ['name' => sprintf('%s %s', $this->first_name, $this->last_name)] : []),
            'metadata' => $metadata,
        ];

        // Billing address can vary per payment method and is best kept against that object as it's
        // the only thing we know the address matches.
        // "Home address" is collected only for Gift Aid declarations and is optional, so append it conditionally.
        if (!$this->nullOrBlank($this->home_address_line_1)) {
            $params['address'] = [
                'line1' => $this->home_address_line_1,
            ];

            if (!$this->nullOrBlank($this->home_postcode)) {
                $params['address']['postal_code'] = $this->home_postcode;

                // Should be 'GB' when postcode non-null.
                $params['address']['country'] = $this->home_country_code;
            }
        }

        return $params;
    }

    private function nullOrBlank(?string $value): bool
    {
        return $value === null || trim($value) === '';
    }
}
