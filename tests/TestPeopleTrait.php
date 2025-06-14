<?php

namespace BigGive\Identity\Tests;

use BigGive\Identity\Domain\Person;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

trait TestPeopleTrait
{
    private static string $testPersonUuid = 'b51dcb90-7b81-4779-ab3b-79435cbd9999';
    private static string $testPersonStripeCustomerId = 'cus_aaaaaaaaaaaa11';

    private EntityManagerInterface $em;

    public function setUp(): void
    {
        $this->em = $this->getAppInstance()->getContainer()->get(EntityManagerInterface::class);
    }

    /**
     * @param bool $withId Sets a string UUID; must be false if passing JSON output on to middleware
     *                      that assumes a real UUID object.
     * @return Person
     */
    private function getTestPerson(bool $withId = false, bool $withPassword = true): Person
    {
        $person = new Person();
        $person->first_name = 'Loraine';
        $person->last_name = 'James';
        $person->email_address = 'loraine@hyperdub.net';
        $person->stripe_customer_id = 'cus_aaaaaaaaaaaa11';

        if ($withPassword) {
            $person->raw_password = 'superSecure123';
        }

        if ($withId) {
            $person->setId(Uuid::v4());
        }

        return $person;
    }

    private function getInitialisedPerson(bool $withPassword): Person
    {
        $person = clone $this->getTestPerson(false, $withPassword);
        self::initialisePerson($person, $withPassword);
        return $person;
    }

    public static function initialisePerson(Person $person, bool $withPassword): void
    {
        if ($withPassword) {
            $person->raw_password = 'superSecure123';
        }

        $person->setId(Uuid::fromString(static::$testPersonUuid));
        $person->setStripeCustomerId(static::$testPersonStripeCustomerId);

        // Call same create/update time initialisers as lifecycle hooks
        $person->createdNow();

        // This is pretty much funtionally redundant, but validates our on-update setter
        // in `TimestampsTrait` doesn't crash!
        $person->updatedNow();

        $person->hashPassword();
    }

    private function getStripeCustomerCommonArgs(): array
    {
        return [
            'email' => null,
            'name' => 'Loraine James',
            'metadata' => [
                'environment' => 'test',
                'personId' => static::$testPersonUuid,
            ],
        ];
    }
}
