<?php

namespace BigGive\Identity\Application\Actions;

use BigGive\Identity\Repository\PasswordResetTokenRepository;
use BigGive\Identity\Repository\PersonRepository;
use Laminas\Diactoros\Response\JsonResponse;
use OpenApi\Annotations as OA;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpNotFoundException;
use Symfony\Component\Uid\Uuid;
/**
 * @OA\Get(
 *     path="/v1/password-reset-token/{secret}",
 *     summary="Get details of a secret password reset token",
 *     @OA\PathParameter(
 *         name="secret",
 *         description="Secret token issued to allow the password reset. Use this to check if a token is valid before inviting user to enter their new password",
 *         @OA\Schema(
 *             type="string",
 *             example="EivZrmdxk4YJXQC37Q6Cnu",
 *             pattern="[a-zA-Z0-9]{5-60}",
 *         ),
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Secret token found",
 *         ),
 *     @OA\Response(
 *         response=404,
 *         description="Secret token not found - may have never existed or expired",
 *         )
 *     )
 * )
 */
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
        $secret = Uuid::fromBase58((string) ($args['base58Secret'] ?? throw new HttpNotFoundException($request)));
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