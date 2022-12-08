<?php

namespace BigGive\Identity\Application\Actions;

use BigGive\Identity\Repository\PasswordResetTokenRepository;
use BigGive\Identity\Repository\PersonRepository;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpNotFoundException;
use Symfony\Component\Uid\Uuid;
class GetPasswordResetToken extends Action
{
    public function __construct(
        LoggerInterface $logger,
        private readonly PasswordResetTokenRepository $tokenRepository,
    ) {
        parent::__construct($logger);
    }

    protected function action(Request $request, array $args): Response
    {
        $secret = Uuid::fromBase58((string) ($args['base58secret'] ?? throw new HttpNotFoundException($request)));
        $token = $this->tokenRepository->findForUse($secret);

        if ($token === null) {
            throw new HttpNotFoundException($request);
        }

        return new JsonResponse(
        // we don't really need any response body, the client can just go on the status. But it would feel weird to send
        // an empty response.
            ['valid' => true]
        );
    }
}