<?php

namespace BigGive\Identity\Application\Actions\EmailVerificationToken;

use BigGive\Identity\Application\Actions\Action;
use BigGive\Identity\Domain\EmailVerificationToken;
use BigGive\Identity\Repository\EmailVerificationTokenRepository;
use BigGive\Identity\Repository\PersonRepository;
use Doctrine\Migrations\Configuration\Migration\JsonFile;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpNotFoundException;

class GetEmailVerificationToken extends Action
{
    public function __construct(
        private \DateTimeImmutable $now,
        private PersonRepository $personRepository,
        private EmailVerificationTokenRepository $emailVerificationTokenRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    /**
     * Given that the user knows (i.e. has a link that encodes) their secret token number
     * and their Person UUID, then they should be able to get a response that tells them:
     *  Whether the token is still valid (e.g. not expired), and if so:
     *  The Email address, first name, and last name on the associated account.
     */
    protected function action(Request $request, array $args): Response
    {
        $tokenSecretSupplied = $this->resolveArg($args, $request, 'secret');
        if (! is_string($tokenSecretSupplied)) {
            throw new HttpNotFoundException($request);
        }

        $person = $this->personRepository->find($this->resolveArg($args, $request, 'personId'));
        $email_address = $person?->email_address;

        if (!$person || $email_address === null) {
            throw new HttpNotFoundException($request);
        }

        if ($person->has_password) {
            throw new HttpNotFoundException($request);
        }

        $oldestAllowedTokenCreationDate = $this->now->modify('-8 hours');

        $token = $this->emailVerificationTokenRepository->findToken(
            email_address: $email_address,
            tokenSecret: $tokenSecretSupplied,
            createdSince: $oldestAllowedTokenCreationDate
        );

        if (!$token) {
            throw new HttpNotFoundException($request);
        }

        return new JsonResponse([
            'token' => [
                'valid' => true,
                'email_address' => $email_address,
                'first_name' => $person->first_name,
                'last_name' => $person->last_name,
            ]
        ]);
    }
}
