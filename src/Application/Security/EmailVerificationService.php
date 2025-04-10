<?php

namespace BigGive\Identity\Application\Security;

use BigGive\Identity\Client\Mailer;
use BigGive\Identity\Domain\EmailVerificationToken;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
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
        $token = EmailVerificationToken::createForEmailAddress(emailAddress: $emailAddress, at: $this->now);
        $this->entityManager->persist($token);
        $this->entityManager->flush();

        /**
         * @todo ID-47: Create \Messages\EmailVerificationToken class in library and uncomment below
         *
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
