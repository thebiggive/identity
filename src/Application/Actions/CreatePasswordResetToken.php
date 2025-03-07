<?php

namespace BigGive\Identity\Application\Actions;

use BigGive\Identity\Application\Settings\SettingsInterface;
use BigGive\Identity\Client\Mailer;
use BigGive\Identity\Domain\PasswordResetToken;
use BigGive\Identity\Repository\PasswordResetTokenRepository;
use BigGive\Identity\Repository\PersonRepository;
use Laminas\Diactoros\Response\JsonResponse;
use OpenApi\Annotations as OA;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpBadRequestException;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 *  @OA\Post(
 *     path="/v1/password-reset-token",
 *     summary="Create a password reset token",
 *     operationId="password_reset_token_create",
 *     security={
 *         {"captcha": {}}
 *     },
 *     @OA\RequestBody(
 *         description="",
 *         required=true,
 *         @OA\JsonContent(
 *              @OA\Property(property="email_address", type="string", example="fred@example.com"),
 *        )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Token created and emailed to user, if they exist, which the server does
 * not confirm or deny via http",
 *         @OA\JsonContent(),
 *        ),
 *     @OA\Response(
 *         response=400,
 *         description="Email address is invalid, e.g. doesn't create @ sign. The absence of this
 * error doesn't mean the email is registered with us or exists, just that the format looks OK..",
 *         @OA\JsonContent(),
 *        )
 *     )
 *   )
 * )
 */
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

    protected function action(Request $request, array $args): Response
    {
        // I'd prefer to just inject the baseUri instead of the entire settings, but this seems easier for now.
        /** @var array $settings */
        $settings = $this->settings->get('accountManagement');
        $accountManagementBaseUrl = ($settings['baseUri']);
        \assert(is_string($accountManagementBaseUrl));

        /** @var array $decoded */
        $decoded = json_decode($request->getBody()->__toString(), true, 512, \JSON_THROW_ON_ERROR);
        $email = (string) $decoded['email_address'];
        $violations = $this->validator->validate($email, constraints: new Email(mode: Email::VALIDATION_MODE_HTML5));
        if (count($violations) > 0) {
            throw new HttpBadRequestException($request, 'Invalid email address');
        }

        $person = $this->personRepository->findPasswordEnabledPersonByEmailAddress($email);

        if ($person === null) {
            // do not let the client know that we didn't find the person.
            return new JsonResponse([]);
        }


        $token = PasswordResetToken::random($person);

        $resetLink = $accountManagementBaseUrl . '/reset-password' . "?token=" . urlencode($token->toBase58Secret());

        $email = $person->email_address;

        if ($email === null) {
            throw new \Exception('Missing email address for person ' . ($person->getId() ?? 'null'));
        }

        $this->mailer->sendEmail([
            'templateKey' => 'password-reset-requested',
            'recipientEmailAddress' => $email,
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
