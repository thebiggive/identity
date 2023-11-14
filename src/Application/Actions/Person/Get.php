<?php

declare(strict_types=1);

namespace BigGive\Identity\Application\Actions\Person;

use BigGive\Identity\Application\Actions\Action;
use BigGive\Identity\Client\Stripe;
use BigGive\Identity\Domain\Person;
use BigGive\Identity\Repository\PersonRepository;
use Laminas\Diactoros\Response\TextResponse;
use OpenApi\Annotations as OA;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpNotFoundException;
use Symfony\Component\Serializer\Encoder\JsonEncode;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @OA\Get(
 *     path="/v1/people/{personId}",
 *     @OA\PathParameter(
 *         name="personId",
 *         description="UUID of the person",
 *         @OA\Schema(
 *             type="string",
 *             format="uuid",
 *             example="f7095caf-7180-4ddf-a212-44bacde69066",
 *             pattern="[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}",
 *         ),
 *     ),
 *     summary="Get a Person",
 *     operationId="person_get",
 *     security={
 *         {"personJWT": {}}
 *     },
 *     @OA\Response(
 *         response=200,
 *         description="Person found",
 *         @OA\JsonContent(ref="#/components/schemas/Person")
 *     ),
 *     @OA\Response(
 *         response=401,
 *         description="JWT token verification failed",
 *     ),
 * ),
 * @see Person
 */
class Get extends Action
{
    /**
     * The campaign/project name for tips committed when preparing a bank transfer /
     * donation funds topup. These are stored as donations to Big Give since they are
     * standalone rather than an adjustment to another main donation.
     */
    public const FUND_TIPS_CAMPAIGN_NAME = 'Big Give General Donations';

    public function __construct(
        LoggerInterface $logger,
        private readonly PersonRepository $personRepository,
        private readonly SerializerInterface $serializer,
        private readonly Stripe $stripeClient,
    ) {
        parent::__construct($logger);
    }

    /**
     * @param array $args
     * @return Response
     * @throws HttpBadRequestException
     * @throws HttpNotFoundException
     */
    protected function action(Request $request, array $args): Response
    {
        /** @var Person|null $person */
        $person = $this->personRepository->find($this->resolveArg($args, $request, 'personId'));
        if (!$person) {
            throw new HttpNotFoundException($request, 'Person not found');
        }

        $includeTipBalances = ($request->getQueryParams()['withTipBalances'] ?? 'false') === 'true';

        if ($person->stripe_customer_id) {
            $stripeCustomer = $this->stripeClient->customers->retrieve($person->stripe_customer_id, [
                'expand' => ['cash_balance'],
            ]);

            // The hash must be non-null and reconciliation automatic for us to consider balances potentially
            // spendable. Note this does _not_ imply that any balances are non-zero right now, just that we
            // should check balances.
            $balanceIsApplicable = (
                !empty($stripeCustomer->cash_balance) &&
                $stripeCustomer->cash_balance->available !== null &&
                $stripeCustomer->cash_balance->settings->reconciliation_mode === 'automatic'
            );

            if ($balanceIsApplicable) {
                foreach ($stripeCustomer->cash_balance->available->toArray() as $currenyCode => $amount) {
                    if ($amount > 0) {
                        $person->cash_balance[$currenyCode] = $amount;
                    }
                }
            }

            if ($includeTipBalances) {
                // If the pending tips balance was requested (e.g. by the transfer funds form),
                // check for the sum of all relevant payment intents.
                $tipPaymentIntentsPending = $this->stripeClient->paymentIntents->all([
                    'customer' => $person->stripe_customer_id,
                ]);

                foreach ($tipPaymentIntentsPending->autoPagingIterator() as $paymentIntent) {
                    $paymentIntentIsPendingDononFundsTip = (
                        $paymentIntent->status === 'requires_action' &&
                        $paymentIntent->payment_method_types === ['customer_balance'] &&
                        $paymentIntent->metadata->campaignName === self::FUND_TIPS_CAMPAIGN_NAME
                    );

                    if (!$paymentIntentIsPendingDononFundsTip) {
                        continue;
                    }

                    $currencyCode = $paymentIntent->currency;
                    if (!isset($person->pending_tip_balance[$currencyCode])) {
                        $person->pending_tip_balance[$currencyCode] = 0;
                    }

                    $person->pending_tip_balance[$currencyCode] += $paymentIntent->amount;
                }
            }
        }

        return new TextResponse(
            $this->serializer->serialize(
                $person,
                'json',
                [
                    AbstractNormalizer::IGNORED_ATTRIBUTES => Person::NON_SERIALISED_ATTRIBUTES,
                    JsonEncode::OPTIONS => JSON_FORCE_OBJECT,
                ],
            ),
            200,
            ['content-type' => 'application/json']
        );
    }
}
