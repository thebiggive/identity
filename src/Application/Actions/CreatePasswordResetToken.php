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
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class CreatePasswordResetToken extends Action
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
        $email = json_decode($this->request->getBody(), true)['email_address'];
        $violations = $this->validator->validate($email, constraints: new Email());
        if (count($violations) > 0) {
            throw new HttpBadRequestException($this->request, 'Invalid email address');
        }

        $person = $this->personRepository->findPasswordEnabledPersonByEmailAddress($email);

        if ($person === null) {
            // do not let the client know that we didn't find the person.
            return new JsonResponse([]);
        }

        $token = new PasswordResetToken($person);

        $this->mailer->sendEmail([
            'templateKey' => 'password-reset-requested',
            'recipientEmailAddress' => $person->email_address,
            'resetLink' => 'https://example.com/' . $token->toBase58Secret(), // @todo work out proper link, possibly as part of DON-272
            'first_name' => $person->first_name,
           'last_name' => $person->last_name,
        ]);

        $this->tokenRepository->persist($token);

        return new JsonResponse([]);
    }
}
