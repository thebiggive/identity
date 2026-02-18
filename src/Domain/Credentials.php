<?php

declare(strict_types=1);

namespace BigGive\Identity\Domain;

use JsonSerializable;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @see Person
 */
#[OA\Schema(
    required: ['email_address', 'raw_password'],
    description: 'Model representing a login attempt; not ORM-backed but the values should correspond to a Person',
)]
class Credentials implements JsonSerializable
{
    /**
     * @OA\Property(
     *  description="One-time code for a solved captcha; required for login",
     *  type="string",
     *  example="some-token-123",
     * )
     */
    public ?string $captcha_code = null;

    /**
     * @Assert\NotBlank()
     * @OA\Property(
     *  property="email_address",
     *  format="email",
     *  example="loraine@example.org",
     * )
     * @var string The email address of the person. Email address must be unique.
     */
    public string $email_address;

    /**
     * @OA\Property(
     *  property="raw_password",
     *  description="Plain text password",
     *  type="string",
     *  format="password",
     *  example="mySecurePassword123",
     * )
     * @var string|null Used on create; only hash of this is persisted.
     * @see Person::validateCaptchaExistsIfNew()
     */
    public ?string $raw_password = null;

    #[\ReturnTypeWillChange]
    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}
