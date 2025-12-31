<?php

namespace BigGive\Identity\Application\Actions\Person;

use Assert\Assertion;
use BigGive\Identity\Application\Actions\Action;
use BigGive\Identity\Domain\Person;
use BigGive\Identity\Repository\PersonRepository;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpNotFoundException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Allows an authenticated user with a password to set or update their home address
 */
class PutAddress extends Action
{
    public function __construct(
        LoggerInterface $logger,
        private readonly ValidatorInterface $validator,
        private PersonRepository $personRepository
    ) {
        parent::__construct($logger);
    }
    protected function action(Request $request, array $args): Response
    {
        $person = $this->personRepository->find($this->resolveArg($args, $request, 'personId'));
        if (!$person) {
            throw new HttpNotFoundException($request, 'Person not found');
        }

        /** @var array $body */
        $body = \json_decode(json: (string) $request->getBody(), associative: true, depth: \JSON_THROW_ON_ERROR);

        $addressLine1 = $body['addressLine1'] ?? throw new HttpBadRequestException($request, 'missing addressLine');
        Assertion::string($addressLine1);
        $postcode = $body['postcode'] ?? throw new HttpBadRequestException($request, 'missing postcode');
        Assertion::string($postcode);
        $countryCode = $body['countryCode'] ?? throw new HttpBadRequestException($request, 'missing country code');
        Assertion::string($countryCode);

        $person->home_address_line_1 = $addressLine1;
        $person->home_postcode = $postcode;
        $person->home_country_code = $countryCode;

        $violations = $this->validator->validate($person, null, [Person::VALIDATION_COMPLETE]);

        if (count($violations) > 0) {
            $message = $this->violationsToPlainText($violations);
            $htmlMessage = $this->violationsToHtml($violations);

            return $this->validationError(
                logMessage: $message,
                publicMessage: null,
                reduceSeverity: true,
                htmlMessage: $htmlMessage,
            );
        }

        $this->personRepository->persist($person, false);

        return new JsonResponse([]);
    }
}
