<?php

declare(strict_types=1);

namespace BigGive\Identity\Repository;

use BigGive\Identity\Client\Mailer;
use BigGive\Identity\Domain\DomainException\DuplicateEmailAddressWithPasswordException;
use BigGive\Identity\Domain\Person;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ObjectRepository;

class PersonRepository
{
    public const EMAIL_IF_PASSWORD_UNIQUE_INDEX_NAME = 'email_if_password';
    private Mailer $mailerClient;

    /** @var EntityRepository<Person> */
    private EntityRepository $entityRepository;

    private EntityManagerInterface $em;


    /**
     * @param EntityRepository<Person> $entityRepository
     */
    public function __construct(EntityManagerInterface $em, EntityRepository $entityRepository)
    {
        $this->em = $em;
        $this->entityRepository = $entityRepository;
    }

    public function find(string $id): ?Person
    {
        return $this->entityRepository->find($id);
    }

    public function findPasswordEnabledPersonByEmailAddress(string $emailAddress): ?Person
    {
        $qb = new QueryBuilder($this->em);
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

        $this->em->persist($person);

        try {
            $this->em->flush();
        } catch (UniqueConstraintViolationException $exception) {
            $this->em->detach($person);
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
        $this->em->persist($person);
        $this->em->flush();
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
//
//    public function findAll()
//    {
//        return $this->entityRepository->findAll();
//    }
//
//    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null)
//    {
//        return $this->entityRepository->findBy($criteria, $orderBy, $limit, $offset);
//    }
//
//    public function findOneBy(array $criteria)
//    {
//        return $this->entityRepository->findOneBy($criteria);
//    }
//
//    public function getClassName()
//    {
//        return Person::class;
//    }
}
