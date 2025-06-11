<?php

namespace BigGive\Identity\Application\Security;

use BigGive\Identity\Domain\EmailVerificationToken;
use BigGive\Identity\Repository\PersonRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\RoutableMessageBus;

class EmailVerificationService
{
    /**
     * @psalm-suppress PossiblyUnusedMethod - used by DI container.
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
        private RoutableMessageBus $bus,
        private PersonRepository $personRepository,
        private \DateTimeImmutable $now,
    ) {
    }

    /**
     * Generates a verification token, stores it locally and will send it to matchbot, but
     * *does not* email it the person, as instead matchbot may include it in a
     * donation thanks message.
     *
     * @param string $emailAddress
     * @return void
     */
    public function storeTokenForEmail(string $emailAddress): void
    {
        if ($this->personRepository->hasPasswordEnabledPersonMatchingEmailAddress($emailAddress)) {
            // no point making a token to set a password when there's already a password set for this account, and
            // we wouldn't allow a second one.
            return;
        }

        $token = EmailVerificationToken::createForEmailAddress(emailAddress: $emailAddress, at: $this->now);
        $this->entityManager->persist($token);
        $this->entityManager->flush();

        /**
         * required so that the code can be included in any donation-thanks emails sent out to this
         * person for them to use when setting their password after donating.
         */

        $this->bus->dispatch(new Envelope(new \Messages\EmailVerificationToken(
            randomCode: $token->random_code,
            emailAddress: $token->email_address,
            createdAt: $token->created_at
        )));
    }
}
