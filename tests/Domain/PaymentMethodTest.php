<?php

declare(strict_types=1);

namespace BigGive\Identity\Tests\Domain;

use BigGive\Identity\Domain\PaymentMethod;
use BigGive\Identity\Domain\Person;
use BigGive\Identity\Tests\TestCase;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;

class PaymentMethodTest extends TestCase
{
    private UuidInterface $personId;
    private PaymentMethod $paymentMethod;

    public function setUp(): void
    {
        $em = $this->getAppInstance()->getContainer()->get(EntityManagerInterface::class);

        $person = new Person();
        $this->personId = $person->id = (new UuidGenerator())->generateId($em, $person);
        $person->first_name = 'Loraine';
        $person->last_name = 'James';

        $this->paymentMethod = new PaymentMethod();
        $this->paymentMethod->id = (new UuidGenerator())->generateId($em, $this->paymentMethod);
        $this->paymentMethod->setPerson($person);
        $this->paymentMethod->token = 'pm_test123';
        $this->paymentMethod->billing_first_address_line = '1 Main St';
        $this->paymentMethod->billing_postcode = 'X1 1YZ';
        $this->paymentMethod->billing_country_code = 'GB';
    }

    public function testGetters(): void
    {
        $this->assertEquals($this->personId, $this->paymentMethod->getPerson()->getId());
    }

    public function testJsonSerialize(): void
    {
        $jsonArray = $this->paymentMethod->jsonSerialize();

        $this->assertEquals('stripe', $jsonArray['psp']);
        $this->assertEquals($this->personId, $jsonArray['person_id']);
        $this->assertEquals('pm_test123', $jsonArray['token']);
        $this->assertEquals('1 Main St', $jsonArray['billing_first_address_line']);
        $this->assertEquals('X1 1YZ', $jsonArray['billing_postcode']);
        $this->assertEquals('GB', $jsonArray['billing_country_code']);
    }
}
