<?php

namespace BigGive\Identity\Application\Actions\EmailVerificationToken;

use Assert\AssertionFailedException;
use BigGive\Identity\Application\Actions\Action;
use BigGive\Identity\Client\Mailer;
use BigGive\Identity\Domain\EmailVerificationToken;
use BigGive\Identity\Domain\Person;
use BigGive\Identity\Repository\PersonRepository;
use Doctrine\ORM\EntityManagerInterface;
use Laminas\Diactoros\Response\JsonResponse;
use OpenApi\Annotations as OA;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpBadRequestException;

/**
 *  @OA\Post(
 *     path="/v1/emailVerificationToken",
 *     summary="Create a an email address verification token in advance of creating a donor account",
 *     operationId="email_verification_token_create",
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
 *         response=201,
 *         description="Token created and emailed to user, if they are NOT already registered exist, which the server
 *          does not confirm or deny via http. If they are already registered then they are sent a message to remind
 *          them of that.",
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
class Create extends Action
{
    public function __construct(
        private \DateTimeImmutable $now,
        private EntityManagerInterface $em,
        private Mailer $mailer,
        private PersonRepository $personRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    /**
     * Creates an email verification token for a given email address, and sends the secret as a code to type
     * (not a link) to that address. Most importantly it does not reveal the secret to the client.
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

        $emailAddress = (string)($requestBody["emailAddress"] ?? throw new HttpBadRequestException($request));

        $existingAccount = $this->personRepository->findPasswordEnabledPersonByEmailAddress($emailAddress);

        try {
            $token = EmailVerificationToken::createForEmailAddress($emailAddress, $this->now);
        } catch (AssertionFailedException $exception) {
            return $this->validationError(
                $exception->getMessage(),
            );
        }

        if ($existingAccount) {
            $this->sendEmailThatAccountAlreadyRegistered($emailAddress, $existingAccount);
        } else {
            $this->persistAndSendToken($token, $emailAddress);
        }

        $this->em->flush();

        return new JsonResponse([], 201);
    }

    private function persistAndSendToken(EmailVerificationToken $token, string $emailAddress): void
    {
        $this->em->persist($token);

        $this->mailer->sendEmail([
            'templateKey' => 'new-account-email-verification',
            'recipientEmailAddress' => $emailAddress,
            'params' => [
                'secretCode' => $token->random_code
            ],
        ]);
    }

    private function sendEmailThatAccountAlreadyRegistered(string $emailAddress, Person $existingAccount): void
    {
        $this->mailer->sendEmail([
            'templateKey' => 'new-account-email-already-registered',
            'recipientEmailAddress' => $emailAddress,
            'params' => [
                'firstName' => $existingAccount->getFirstName(),
            ],
        ]);
    }
}
