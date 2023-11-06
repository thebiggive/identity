<?php

namespace BigGive\Identity\Application\Commands;

use BigGive\Identity\Domain\Person;
use BigGive\Identity\Repository\PersonRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * For use in dev environments only. Adds a preset list of users to the database for use in manual testing.
 */
#[AsCommand(name: 'identity:populate-test-users')]
class PopulateUsers extends Command
{
    public function __construct(private PersonRepository $personRepository)
    {
        parent::__construct();
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        if (getenv('APP_ENV') !== 'local') {
            throw new \Exception('Populate users command is for local dev environments only');
        }

        $relativePath = 'tests/local-users.php';
        $filename = __DIR__ . '/../../../' . $relativePath . '';

        if (! file_exists($filename)) {
            $output->writeln("File not found at $relativePath.");
            $output->writeln("If you wish to have users populated, copy $relativePath.example to $relativePath and edit");
            return 0;
        }

        /** @var list<list<string>> $personDetails */

        $personDetails =  require $filename;

        foreach ($personDetails as $personDetail) {
            $person = new Person();
            $person->first_name = $personDetail[0];
            $person->last_name = $personDetail[1];
            $person->email_address = $personDetail[2];
            $person->stripe_customer_id = $personDetail[3];
            $person->raw_password = $personDetail[4];
            $person->home_address_line_1 = "home address line 1";
            $person->home_postcode = "PSTCD";

            $this->personRepository->persist($person);

            $output->writeln("Added {$person->first_name} {$person->last_name} <{$person->email_address}> to DB");
        }
        return 0;
    }
}