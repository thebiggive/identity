<?php

namespace BigGive\Identity\Application\Actions\EmailVerificationToken;

use Assert\AssertionFailedException;
use BigGive\Identity\Application\Actions\Action;
use BigGive\Identity\Domain\EmailVerificationToken;
use Doctrine\ORM\EntityManagerInterface;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpBadRequestException;

class Create extends Action
{
    public function __construct(
        private \DateTimeImmutable $now,
        private EntityManagerInterface $em,
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

        $emailAddress = (string) ($requestBody["emailAddress"] ?? throw new HttpBadRequestException($request));

        try {
            $token = EmailVerificationToken::createForEmailAddress($emailAddress, $this->now);
        } catch (AssertionFailedException $exception) {
            return $this->validationError(
                $exception->getMessage(),
            );
        }

        $this->em->persist($token);
        $this->em->flush();

        return new JsonResponse([], 201);
    }
}
