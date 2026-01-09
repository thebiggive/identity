<?php

namespace BigGive\Identity\Application\Actions\Person;

use BigGive\Identity\Application\Actions\Action;
use BigGive\Identity\Application\Actions\ActionError;
use BigGive\Identity\Application\Auth\Token;
use BigGive\Identity\Application\Auth\TokenService;
use BigGive\Identity\Client\Mailer;
use BigGive\Identity\Domain\DomainException\DuplicateEmailAddressWithPasswordException;
use BigGive\Identity\Domain\EmailVerificationToken;
use BigGive\Identity\Domain\Person;
use BigGive\Identity\Repository\EmailVerificationTokenRepository;
use BigGive\Identity\Repository\PersonRepository;
use Laminas\Diactoros\Response\JsonResponse;
use OpenApi\Annotations as OA;
use BigGive\Identity\Client;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpBadRequestException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Sets a password on a donor account for the first time. We only allow this if the user knows
 * the secret email verification token code {@see EmailVerificationToken}, and the UUID of the account.
 *
 * They will get both encoded in a link when they make a donation.
 *
 * @OA\Post(
 *     path="/v1/people/setFirstPassword",
 *     summary="Set a password for a previously anonymous person account",
 *     @OA\RequestBody(
 *         description="",
 *         required=true,
 *         @OA\JsonContent(
 *              @OA\Property(property="personUuid", type="string", example="7bb10832-1acd-11f0-8cc1-836914a6fa41"),
 *              @OA\Property(property="secret", type="string", example="654321"),
 *              @OA\Property(property="password", type="string", example="correct horse battery staple"),
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Password changed",
 *         @OA\JsonContent(),
 *     ),
 *      @OA\Response(
 *         response=400,
 *         description="Returned if the new password is bad (e.g. too short), or it the secret token is
 * invalid or expired",
 *         @OA\JsonContent(),
 *     ),
 * ),
 * @link https://stripe.com/docs/payments/customer-balance/funding-instructions?bt-region-tabs=uk
 */
class SetFirstPassword extends Action
{
    public function __construct(
        private \DateTimeImmutable $now,
        private readonly ValidatorInterface $validator,
        private PersonRepository $personRepository,
        private EmailVerificationTokenRepository $emailVerificationTokenRepository,
        private Mailer $mailerClient,
        private Client\Stripe $stripeClient,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    public function sendRegisteredEmail(Person $person): void
    {
        $this->mailerClient->sendEmail($person->toMailerPayload());
    }

    protected function action(Request $request, array $args): Response
    {
        try {
            $requestBody = json_decode(
                (string)$request->getBody(),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
            \assert(is_array($requestBody));
        } catch (\JsonException $exception) {
            return $this->validationError(
                $exception->getMessage(),
            );
        }

        $uuid = (string) ($requestBody["personUuid"] ?? throw new HttpBadRequestException($request));
        $secret = (string) ($requestBody["secret"] ?? throw new HttpBadRequestException($request));
        $newPassword = (string) ($requestBody["password"] ?? throw new HttpBadRequestException($request));

        $person = $this->personRepository->find($uuid);
        if ($person === null || $person->email_address === null) {
            throw new HttpBadRequestException($request);
        }

        // We allow slightly older tokens here than in GetEmailVerificationToken to account for time spent looking
        // at the token before using it.
        $oldestAllowedTokenCreationDate =
            $this->now->sub(new \DateInterval('PT' . TokenService::COMPLETE_ACCOUNT_VALIDITY_PERIOD_SECONDS . 'S'));
        $token = $this->emailVerificationTokenRepository->findToken(
            email_address: $person->email_address,
            tokenSecret: $secret,
            createdSince: $oldestAllowedTokenCreationDate
        );

        if ($token === null) {
            // important that this is the same response as if the person was not found - we don't want to make
            // it easy to know whether the person record exists if the client doesn't know the token.
            throw new HttpBadRequestException($request);
        }

        $personHadPassword = $person->getPasswordHash() !== null;
        if ($personHadPassword) {
            throw new HttpBadRequestException($request, 'Password already exists');
        }

        $person->raw_password = $newPassword;

        $violations = $this->validator->validate($person, null, [Person::VALIDATION_COMPLETE]);
        if (count($violations) > 0) {
            $message = $this->violationsToPlainText($violations);
            $htmlMessage = $this->violationsToHtml($violations);

            return $this->validationError(
                $message,
                $message,
                true,
                htmlMessage: $htmlMessage,
            );
        }

        // Assuming the secret above is correct we know that the person using this has access to the email address.
        // Setting email_address_verified may unlock some functionality for them in the future, e.g.
        // seeing donations made while not logged in, and perhaps regular giving.
        $person->email_address_verified = $this->now;

        // code below duplicates from the Person\Update route but will be removed from there soon when
        // we require all new passworded accounts to have verified email addresses. However may duplicate with
        // another route we will create similar to this that creates a new account in a single step using a token
        // that doesn't relate to an existing account.
        try {
            // We should persist Stripe's Customer ID on initial Person create.
            \assert(is_string($person->stripe_customer_id));
            $this->personRepository->persist($person, false);
            $params = $person->getStripeCustomerParams();

            unset($params['test_clock']); // these params not accepted by stripe for updated, and stripe library is now
            // strictly typed.
            unset($params['payment_method']);
            unset($params['tax_id_data']);

            $this->stripeClient->customers->update($person->stripe_customer_id, $params);
            $this->sendRegisteredEmail($person);
        } catch (DuplicateEmailAddressWithPasswordException $duplicateException) {
            $this->logger->warning(sprintf(
                '%s failed to persist Person: %s',
                __CLASS__,
                $duplicateException->getMessage(),
            ));

            return $this->validationError(
                logMessage: "Update not valid: {$duplicateException->getMessage()}",
                publicMessage: 'Your password could not be set. There is already a password set for ' .
                'your email address.',
                errorType: ActionError::DUPLICATE_EMAIL_ADDRESS_WITH_PASSWORD,
            );
        }

        return new JsonResponse([]);
    }
}
