<?php

namespace BigGive\Identity\Application\Actions\Person;

use BigGive\Identity\Application\Actions\Action;
use BigGive\Identity\Application\Actions\ActionError;
use BigGive\Identity\Domain\DomainException\DuplicateEmailAddressWithPasswordException;
use BigGive\Identity\Domain\EmailVerificationToken;
use BigGive\Identity\Repository\EmailVerificationTokenRepository;
use BigGive\Identity\Repository\PersonRepository;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpNotFoundException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Sets a password on a donor account for the first time. We only allow this if the user knows
 * the secret email verification token code {@see EmailVerificationToken}, and the UUID of the account.
 *
 * They will get both encoded in a link when they make a donation.
 */
class SetFirstPassword extends Action
{
    public function __construct(
        private \DateTimeImmutable $now,
        private readonly ValidatorInterface $validator,
        private PersonRepository $personRepository,
        private EmailVerificationTokenRepository $emailVerificationTokenRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
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

        $uuid = (string) $requestBody["personUuid"];
        $secret = (string) $requestBody["secret"];
        $newPassword = (string) $requestBody["password"];

        $person = $this->personRepository->find($uuid);
        if ($person === null || $person->email_address === null) {
            throw new HttpBadRequestException($request);
        }

        // We allow slightly older tokens here than in GetEmailVerificationToken to account for time spent looking
        // at the token before using it.
        $oldestAllowedTokenCreationDate =
            $this->now->modify('-8 hours')
            ->modify('-5 minutes');

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

        $violations = $this->validator->validate($person, null, ['complete']);
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
        $person->email_address_verified = true;

        // code below duplicates from the Person\Update route but will be removed from there soon when
        // we require all new passworded accounts to have verified email addresses. However may duplicate with
        // another route we will create similar to this that creates a new account in a single step using a token
        // that doesn't relate to an existing account.
        try {
            $this->personRepository->persist($person);
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
