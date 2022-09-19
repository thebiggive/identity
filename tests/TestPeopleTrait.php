<?php

namespace BigGive\Identity\Tests;

use BigGive\Identity\Domain\Person;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

trait TestPeopleTrait
{
    private static string $testPersonUuid = '12345678-1234-1234-1234-1234567890ab';
    private static string $testPersonStripeCustomerId = 'cus_aaaaaaaaaaaa11';

    private EntityManagerInterface $em;

    public function setUp(): void
    {
        $this->em = $this->getAppInstance()->getContainer()->get(EntityManagerInterface::class);
    }

    /**
     * @param bool $withId  Sets a string UUID; must be false if passing JSON output on to middleware
     *                      that assumes a real UUID object.
     * @return Person
     */
    private function getTestPerson(bool $withId = false, bool $withPassword = true): Person
    {
        $person = new Person();
        $person->first_name = 'Loraine';
        $person->last_name = 'James';
        $person->email_address = 'loraine@hyperdub.net';

        if ($withPassword) {
            $person->raw_password = 'superSecure123';
        }

        if ($withId) {
            $person->id = Uuid::v4();
        }

        return $person;
    }

    private function getInitialisedPerson(bool $withPassword): Person
    {
        $person = clone $this->getTestPerson(false, $withPassword);
        $person->setId(Uuid::fromString(static::$testPersonUuid));
        $person->setStripeCustomerId(static::$testPersonStripeCustomerId);

        // Call same create/update time initialisers as lifecycle hooks
        $person->createdNow();
        $person->hashPassword();

        return $person;
    }

    private function getStripeCustomerCommonArgs(): array
    {
        return [
            'metadata' => [
                'personId' => static::$testPersonUuid,
            ],
        ];
    }
}
