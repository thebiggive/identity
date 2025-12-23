<?php

declare(strict_types=1);

namespace BigGive\Identity\Application\Actions\Person;

use Assert\Assertion;
use BigGive\Identity\Application\Actions\Action;
use BigGive\Identity\Application\Security\AuthenticationException;
use BigGive\Identity\Repository\PersonRepository;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpNotFoundException;

class Delete extends Action
{
    public function __construct(
        LoggerInterface $logger,
        private readonly PersonRepository $personRepository,
    ) {
        parent::__construct($logger);
    }

    /**
     * @param array $args
     * @return Response
     * @throws HttpBadRequestException
     */
    protected function action(Request $request, array $args): Response
    {
        if (! \in_array(getenv('APP_ENV'), ['local', 'test'], true)) {
            // new upcoming feature not yet ready for use outside local env.
            throw new HttpNotFoundException($request, \getenv('APP_ENV'));
        }

        $body = \json_decode(json: (string) $request->getBody(), associative: true, depth: \JSON_THROW_ON_ERROR);
        Assertion::isArray($body);
        $person = $this->personRepository->find($this->resolveArg($args, $request, 'personId'));

        if (! $person) {
            throw new HttpNotFoundException($request);
        }

        // for extra security with this irreversible action we require not only that they are logged in but
        // also that they supply their password in the form for this action.
        $passwordSupplied = (string)($body['password'] ?? '');

        try {
            $person->verifyPassword($passwordSupplied);
        } catch (AuthenticationException) {
            throw new HttpBadRequestException($request, 'Password supplied does not match account password');
        }

        $this->personRepository->delete($person);

        return new JsonResponse(['message' => 'Account deleted'], 200);
    }

}
