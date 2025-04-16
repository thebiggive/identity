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
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpNotFoundException;

class GetEmailVerificationTokenNoPersonId extends Action
{
    public function __construct(
        private \DateTimeImmutable $now,
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
        try {
            $requestBody = json_decode(
                (string)$request->getBody(),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
            \assert(is_array($requestBody));
        } catch (\JsonException $exception) {
            return $this->validationError(
                $exception->getMessage(),
            );
        }

        $emailAddress = (string) ($requestBody["emailAddress"] ?? throw new HttpBadRequestException($request));
        $tokenSecretSupplied = (string) ($requestBody["secret"] ?? throw new HttpBadRequestException($request));

        $oldestAllowedTokenCreationDate = EmailVerificationToken::oldestCreationDateForViewingToken($this->now);

        $token = $this->emailVerificationTokenRepository->findToken(
            email_address: $emailAddress,
            tokenSecret: $tokenSecretSupplied,
            createdSince: $oldestAllowedTokenCreationDate
        );

        if (!$token) {
            throw new HttpNotFoundException($request);
        }

        return new JsonResponse([
            'token' => [
                'valid' => true,
                'email_address' => $emailAddress,
                'first_name' => null,
                'last_name' => null,
            ]
        ]);
    }
}
