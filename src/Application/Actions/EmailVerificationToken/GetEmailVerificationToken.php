<?php

namespace BigGive\Identity\Application\Actions\EmailVerificationToken;

use BigGive\Identity\Application\Actions\Action;
use BigGive\Identity\Domain\EmailVerificationToken;
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
    /** @var EntityRepository<EmailVerificationToken>  */
    private EntityRepository $emailVerificationTokenRepository;

    public function __construct(
        private \DateTimeImmutable $now,
        private PersonRepository $personRepository,
        EntityManagerInterface $entityManager,
        LoggerInterface $logger
    ) {
        $this->emailVerificationTokenRepository = $entityManager->getRepository(EmailVerificationToken::class);
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

        if (!$person) {
            throw new HttpNotFoundException($request);
        }

        $tokens = $this->emailVerificationTokenRepository->findBy(
            [
                'email_address' => $person->email_address,
                'random_code' => $tokenSecretSupplied,
            ]
        );
        $oldestAllowedTokenCreationDate = $this->now->modify('-8 hours');

        $matchingToken = null;
        foreach ($tokens as $token) {
            if ($token->created_at > $oldestAllowedTokenCreationDate) {
                $matchingToken = $token;
            }
        }

        if (!$matchingToken) {
            throw new HttpNotFoundException($request);
        }

        return new JsonREsponse([
            'token' => [
                'valid' => true,
                'email_address' => $person->email_address,
                'first_name' => $person->first_name,
                'last_name' => $person->last_name,
            ]
        ]);
    }
}
