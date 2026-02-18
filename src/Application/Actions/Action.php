<?php

declare(strict_types=1);

namespace BigGive\Identity\Application\Actions;

use BigGive\Identity\Domain\DomainException\DomainRecordNotFoundException;
use OpenApi\Annotations as OA;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpNotFoundException;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotCompromisedPassword;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * @OA\Info(title="Big Give Identity service", version="1.0.0"),
 * @OA\Server(
 *     description="Staging",
 *     url="https://identity-staging.thebiggivetest.org.uk",
 * ),
 * @OA\SecurityScheme(
 *     securityScheme="personJWT",
 *     type="apiKey",
 *     in="header",
 *     name="x-tbg-auth",
 * ),
 * @OA\SecurityScheme(
 *      securityScheme="captcha",
 *      type="apiKey",
 *      in="header",
 *      name="x-captcha-code",
 *  ),
 *
 * Swagger Hub doesn't (yet?) support `"bearerFormat": "JWT"`.
 */
abstract class Action
{
    public function __construct(protected readonly LoggerInterface $logger)
    {
    }

    /**
     * @throws HttpNotFoundException
     * @throws HttpBadRequestException
     */
    public function __invoke(Request $request, Response $_response, array $args): Response
    {
        try {
            return $this->action($request, $args);
        } catch (DomainRecordNotFoundException $e) {
            throw new HttpNotFoundException($request, $e->getMessage());
        }
    }

    /**
     * @param array $args
     * @throws DomainRecordNotFoundException
     * @throws HttpBadRequestException
     */
    abstract protected function action(Request $request, array $args): Response;

    public function violationsToHtml(ConstraintViolationListInterface $violations): string
    {
        $violationDetails = [];
        foreach ($violations as $violation) {
            $violationDetails[] = $this->summariseConstraintViolationAsHtmlSnippet($violation);
        }

        return implode('; ', $violationDetails);
    }

    public function violationsToPlainText(ConstraintViolationListInterface $violations): string
    {
        $violationDetails = [];
        foreach ($violations as $violation) {
            $violationDetails[] = $this->summariseConstraintViolation($violation);
        }

        return implode('; ', $violationDetails);
    }

    /**
     * @return mixed
     * @throws HttpBadRequestException
     */
    protected function resolveArg(array $args, Request $request, string $name)
    {
        if (!isset($args[$name])) {
            throw new HttpBadRequestException($request, "Could not resolve argument `{$name}`.");
        }

        return $args[$name];
    }

    /**
     * @param array|object|null $data
     */
    protected function respondWithData($data = null, int $statusCode = 200): Response
    {
        $payload = new ActionPayload($statusCode, $data);

        return $this->respond($payload);
    }

    protected function respond(ActionPayload $payload): Response
    {
        $response = new \Slim\Psr7\Response();
        $json = json_encode($payload, JSON_PRETTY_PRINT);
        $response->getBody()->write($json);

        return $response
                    ->withHeader('Content-Type', 'application/json')
                    ->withStatus($payload->getStatusCode());
    }

    /**
     * @param string        $logMessage
     * @param string|null   $publicMessage  Falls back to $logMessage if null.
     * @param bool          $reduceSeverity Whether to log this error only at INFO level. Used to
     *                                      avoid noise from known issues.
     * @param int|null      $httpCode       Falls back to 400 if null.
     * @return Response with 400 (or custom) HTTP response code.
     */
    protected function validationError(
        string $logMessage,
        ?string $publicMessage = null,
        bool $reduceSeverity = false,
        ?int $httpCode = 400,
        ?string $errorType = null,
        ?string $htmlMessage = null,
    ): Response {
        if ($reduceSeverity) {
            $this->logger->info($logMessage);
        } else {
            $this->logger->warning($logMessage);
        }
        $errorType ??= ($httpCode === 401 ? ActionError::VALIDATION_ERROR : ActionError::BAD_REQUEST);
        $error = new ActionError(
            $errorType,
            $publicMessage ?? $logMessage,
            htmlDescription: $htmlMessage,
        );

        return $this->respond(new ActionPayload($httpCode, null, $error));
    }

    protected function summariseConstraintViolation(ConstraintViolationInterface $violation): string
    {
        if ($violation->getMessage() === 'This value should not be blank.') {
            return "{$violation->getPropertyPath()} must not be blank";
        }

        return $violation->getMessage();
    }

    protected function summariseConstraintViolationAsHtmlSnippet(ConstraintViolationInterface $violation): string
    {
        return match ($violation->getCode()) {
            NotBlank::IS_BLANK_ERROR => htmlspecialchars("{$violation->getPropertyPath()} must not be blank"),

            NotCompromisedPassword::COMPROMISED_PASSWORD_ERROR =>
            /* would prefer not to have color style here, but I'm having trouble
               setting the colour in donate-fronted for some reason. */
            <<<HTML
                We use a password-checking service which has found this password in a data breach. Please choose a
                different one. For more information please read our
                <a style="color: inherit;" href="https://biggive.org/privacy/" target="_blank">Privacy Policy<a/>.
            HTML,

            default => htmlspecialchars((string) $violation->getMessage())
        };
    }
}
