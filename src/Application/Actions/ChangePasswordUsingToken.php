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
        $requestData = json_decode($this->request->getBody(), true);

        $secret = $requestData['secret'] ?? throw new \Exception('Missing secret');

        $secretUuid = Uuid::fromBase58($secret);

        $token = $this->tokenRepository->findBySecret($secretUuid);

        if ($token->created_at < new \DateTime("1 hour ago")) {
            throw new HttpBadRequestException($this->request, 'Token expired');
        }

        $person = $this->personRepository->find($token->person_id->toRfc4122());
        $person->raw_password = $requestData['new-password'];
        // @todo - get Symfony validator to call Person::validatePasswordIfNotBlank

        $this->personRepository->persistForPasswordChange($person);
        return new JsonResponse([]);
    }
}
