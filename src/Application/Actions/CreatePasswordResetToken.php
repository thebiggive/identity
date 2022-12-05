<?php

namespace BigGive\Identity\Application\Actions;

use BigGive\Identity\Application\Settings\SettingsInterface;
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
        private readonly SettingsInterface $settings,
        private readonly Mailer $mailer,
    ) {
        parent::__construct($logger);
    }

    protected function action(): Response
    {
        // I'd prefer to just inject the baseUri instead of the entire settings, but this seems easier for now.
        $accountManagementBaseUrl = ($this->settings->get('accountManagement')['baseUri']);
        \assert(is_string($accountManagementBaseUrl));

        /** @var array $decoded */
        $decoded = json_decode($this->request->getBody()->__toString(), true, 512, \JSON_THROW_ON_ERROR);
        $email = (string) $decoded['email_address'];
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

        $resetLink = $accountManagementBaseUrl . '/reset-password/' . "?token=" . urlencode($token->toBase58Secret());

        $this->mailer->sendEmail([
            'templateKey' => 'password-reset-requested',
            'recipientEmailAddress' => $person->email_address,
            'params' => [
                'firstName' => $person->getFirstName(),
                'lastName' => $person->getLastName(),
                'resetLink' => $resetLink,
            ],
        ]);

        $this->tokenRepository->persist($token);

        return new JsonResponse([]);
    }
}
