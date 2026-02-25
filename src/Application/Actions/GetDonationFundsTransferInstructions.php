<?php

declare(strict_types=1);

namespace BigGive\Identity\Application\Actions;

use BigGive\Identity\Client\Stripe;
use BigGive\Identity\Domain\Person;
use BigGive\Identity\Repository\PersonRepository;
use Laminas\Diactoros\Response\JsonResponse;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpNotFoundException;

/**
 * @link https://stripe.com/docs/payments/customer-balance/funding-instructions?bt-region-tabs=uk
 */
#[OA\Get(
    path: '/v1/people/{personId}/funding_instructions',
    summary: "Get a Person's funding instructions for donation funds top-up by bank transfer",
    operationId: 'funding_instructions_get',
    security: [['personJWT' => []]],
    parameters: [
        new OA\PathParameter(
            name: 'personId',
            description: 'UUID of the person',
            schema: new OA\Schema(
                type: 'string',
                format: 'uuid',
                example: 'f7095caf-7180-4ddf-a212-44bacde69066',
                pattern: '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}',
            ),
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Funding instructions object as documented at ' .
                'https://stripe.com/docs/payments/customer-balance/funding-instructions?bt-region-tabs=uk',
            content: new OA\JsonContent(),
        ),
        new OA\Response(response: 401, description: 'JWT token verification failed'),
    ],
)]
class GetDonationFundsTransferInstructions extends Action
{
    public function __construct(
        LoggerInterface $logger,
        private readonly PersonRepository $personRepository,
        private readonly Stripe $stripeClient,
    ) {
        parent::__construct($logger);
    }

    public function action(Request $request, array $args): ResponseInterface
    {
        $person = $this->personRepository->find($this->resolveArg($args, $request, 'personId'));
        if (!$person) {
            throw new HttpNotFoundException($request, 'Person not found');
        }

        $instructions = $this->stripeClient->customers->createFundingInstructions(
            $person->stripe_customer_id,
            [
                'funding_type' => 'bank_transfer',
                'bank_transfer' => [
                    // Only UK bank support for now.
                    'type' => 'gb_bank_transfer',
                ],
                'currency' => (string)($request->getQueryParams()['currency'] ?? 'gbp'),
            ],
        );

        return new JsonResponse($instructions);
    }
}
