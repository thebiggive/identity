<?php

declare(strict_types=1);

namespace BigGive\Identity\Repository;

use BigGive\Identity\Application\Security\Password;
use BigGive\Identity\Client\Mailer;
use BigGive\Identity\Domain\DomainException\DuplicateEmailAddressWithPasswordException;
use BigGive\Identity\Domain\Person;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;

/**
 * @template-extends EntityRepository<Person>
 */
class PersonRepository extends EntityRepository
{
    public const string EMAIL_IF_PASSWORD_UNIQUE_INDEX_NAME = 'email_if_password';
    private Mailer $mailerClient;

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

    /**
     * This has its own thin repo method largely so we can mock it in tests and simulate
     * side effects like setting a binary UUID, without having tests depend upon a real
     * database.
     *
     * It also sets the password hash and EM-flushes the entity as side effects.
     *
     */
    public function persist(Person $person): void
    {
        $person->hashPassword();

        $em = $this->getEntityManager();
        $em->persist($person);

        try {
            $em->flush();
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
