<?php

declare(strict_types=1);

namespace BigGive\Identity\Repository;

use BigGive\Identity\Client\Mailer;
use BigGive\Identity\Domain\Person;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;
use function PHPUnit\Framework\stringContains;

/**
 * @template-extends EntityRepository<Person>
 */
class PersonRepository extends EntityRepository
{
    public const EMAIL_IF_PASSWORD_UNIQUE_INDEX_NAME = 'email_if_password';
    private Mailer $mailerClient;

    public function __construct(EntityManagerInterface $em, ClassMetadata $class)
    {
        parent::__construct($em, $class);
    }

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

        $this->getEntityManager()->persist($person);

        try {
            $this->getEntityManager()->flush();
        } catch (UniqueConstraintViolationException $exception) {
            if (str_contains($exception->getMessage(), self::EMAIL_IF_PASSWORD_UNIQUE_INDEX_NAME)) {
                \assert(($person->email_address !== null));
                throw new \LogicException(sprintf(
                    'Person already exists with password and email address %s',
                    $person->email_address
                ));
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
}
