<?php

declare(strict_types=1);

namespace BigGive\Identity\Repository;

use BigGive\Identity\Application\Security\Password;
use BigGive\Identity\Client\Mailer;
use BigGive\Identity\Domain\DomainException\DuplicateEmailAddressWithPasswordException;
use BigGive\Identity\Domain\Person;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\RoutableMessageBus;

/**
 * @template-extends EntityRepository<Person>
 */
class PersonRepository extends EntityRepository
{
    public const string EMAIL_IF_PASSWORD_UNIQUE_INDEX_NAME = 'email_if_password';

    // Non-baselined properties nullable for now to swerve MissingConstructor warnings upon use. Best eventual
    // fix is probably to avoid getRepository() entirely so we can use conventional DI.
    private ?LoggerInterface $logger = null;
    private Mailer $mailerClient;
    private ?RoutableMessageBus $bus = null;

    public function findPasswordEnabledPersonByEmailAddress(string $emailAddress): ?Person
    {
        $qb = new QueryBuilder($this->getEntityManager());
        $qb->select('p')
            ->from(Person::class, 'p')
            ->where('p.email_address = :emailAddress')
            ->andWhere('p.password IS NOT NULL')
            ->setParameter('emailAddress', $emailAddress);

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function hasPasswordEnabledPersonMatchingEmailAddress(string $emailAddress): bool
    {
        $qb = new QueryBuilder($this->getEntityManager());
        $result = $qb->select('p')
            ->from(Person::class, 'p')
            ->where('p.email_address = :emailAddress')
            ->andWhere('p.password IS NOT NULL')
            ->setParameter('emailAddress', $emailAddress)
            ->getQuery()
            ->getOneOrNullResult();

        \assert($result === null || $result instanceof Person);

        return $result !== null;
    }

    /**
     * This has its own thin repo method largely so we can mock it in tests and simulate
     * side effects like setting a binary UUID, without having tests depend upon a real
     * database.
     *
     * It also sets the password hash and EM-flushes the entity as side effects.
     *
     * @param bool $skipMatchbotSync - don't attempt to send person record to matchbot, even if password is set.
     * We can't send them if they don't have a stripe ID yet. @todo consider moving the sync operation out of the
     * persist to avoid this issue.
     */
    public function persist(Person $person, bool $skipMatchbotSync): void
    {
        $person->hashPassword();

        $em = $this->getEntityManager();
        $em->persist($person);

        try {
            $em->flush();

            if ($person->getPasswordHash() !== null && ! $skipMatchbotSync) {
                $personMessage = $person->toMatchBotSummaryMessage();

                // DI setup in repositories.php sets these in all production code where we have the repo.
                \assert($this->bus !== null);
                \assert($this->logger !== null);
                $this->logger->info(sprintf("Will dispatch message about person %s", $personMessage->id));
                $this->bus->dispatch(new Envelope($personMessage));
            }
        } catch (UniqueConstraintViolationException $exception) {
            $em->detach($person);
            if (str_contains($exception->getMessage(), self::EMAIL_IF_PASSWORD_UNIQUE_INDEX_NAME)) {
                \assert(($person->email_address !== null));
                throw new DuplicateEmailAddressWithPasswordException(sprintf(
                    'Person already exists with password and email address %s',
                    $person->email_address
                ), 0, $exception);
            }

            throw $exception;
        }
    }

    public function persistForPasswordChange(Person $person): void
    {
        $person->hashPassword();
        $this->getEntityManager()->persist($person);
        $this->getEntityManager()->flush();
    }

    /**
     * This gets its own method instead so we can use `DefaultRepositoryFactory` to load
     * the repo and not worry about constructor args.
     */
    public function setMailerClient(Mailer $mailerClient): void
    {
        $this->mailerClient = $mailerClient;
    }

    public function setBus(RoutableMessageBus $bus): void
    {
        $this->bus = $bus;
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    public function sendRegisteredEmail(Person $person): bool
    {
        return $this->mailerClient->sendEmail($person->toMailerPayload());
    }

    /**
     * Generates and persists a new password hash for this person if our existing hash for them wasn't made
     * using our current algorithm and settings.
     *
     * Must only be called for a person who has a password.
     */
    public function upgradePasswordIfPossible(string $raw_password, Person $person): void
    {
        $hash = $person->getPasswordHash();

        if ($hash === null) {
            throw new \LogicException('upgradePasswordIfPossible() called on passwordless Person');
        }

        if (Password::needsRehash($hash)) {
            $person->raw_password = $raw_password;
            $this->persistForPasswordChange($person);
        }
    }
}
