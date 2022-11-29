<?php

namespace BigGive\Identity\Application\Actions;

use BigGive\Identity\Client\Mailer;
use BigGive\Identity\Domain\PasswordResetToken;
use BigGive\Identity\Repository\PasswordResetTokenRepository;
use BigGive\Identity\Repository\PersonRepository;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpBadRequestException;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ChangePasswordUsingToken extends Action
{
    public function __construct(
        LoggerInterface $logger,
        private readonly PersonRepository $personRepository,
        private readonly PasswordResetTokenRepository $tokenRepository,
        private readonly ValidatorInterface $validator,
        private readonly Mailer $mailer,
    ) {
        parent::__construct($logger);
    }

    protected function action(): Response
    {
        /** @var array $requestData */
        $requestData = json_decode($this->request->getBody(), true);

        $secret = $requestData['secret'] ?? throw new \Exception('Missing secret');

        $secretUuid = Uuid::fromBase58((string) $secret);

        $token = $this->tokenRepository->findForUse($secretUuid);

        if ($token === null) {
            throw new HttpBadRequestException($this->request, 'Token not found or not valid');
        }

        // The following two checks should be not necassary in production, because they are done in the DQL query
        // when we called findForUse. But leaving them in for now for belt-and-braces and because they are unit tested
        // but we don't have a way to unit test DQL.
        if ($token->created_at < new \DateTime("1 hour ago")) {
            throw new HttpBadRequestException($this->request, 'Token not found or not valid');
        }
        if ($token->isUsed()) {
            throw new HttpBadRequestException($this->request, 'Token not found or not valid');
        }

        $person = $token->person;
        $person->raw_password = (string) $requestData['new-password'];
        // @todo - get Symfony validator to call Person::validatePasswordIfNotBlank

        $this->personRepository->persistForPasswordChange($person);
        $token->setUsed(new \DateTimeImmutable());
        $this->tokenRepository->persist($token);

        return new JsonResponse([]);
    }
}
