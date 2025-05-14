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
use Stripe\PaymentIntent;
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
    public const string FUND_TIPS_CAMPAIGN_NAME = 'Big Give General Donations';

    public const array STATUSES_THAT_MAY_BE_PENDING_BANK_TRANSFER = [
        PaymentIntent::STATUS_REQUIRES_ACTION,
        PaymentIntent::STATUS_REQUIRES_CONFIRMATION,
        PaymentIntent::STATUS_REQUIRES_PAYMENT_METHOD,
    ];

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
            /**
             * @psalm-suppress InvalidArrayAccess (we may be not using the stripe SDK exactly right, but this is a
             * very frequently called function so if it didn't work we would know about it.)
             */
            $balanceIsApplicable = (
                !empty($stripeCustomer->cash_balance) &&
                $stripeCustomer->cash_balance->available !== null &&
                $stripeCustomer->cash_balance->settings['reconciliation_mode'] === 'automatic'
            );

            if ($balanceIsApplicable) {
                foreach ($stripeCustomer->cash_balance->available->toArray() as $currenyCode => $amount) {
                    if ($amount > 0) {
                        $person->cash_balance[$currenyCode] = $amount;
                    }
                }
            }

            if ($includeTipBalances) {
                $this->setTipBalances($person);
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

    /**
     * Check for the sum of all relevant payment intents and set those on person object to be returned to client
     */
    private function setTipBalances(Person $person): void
    {
        if (!$this->stripeClient->paymentIntents) {
            throw new \LogicException('Stripe paymentIntents service not available in current mode');
        }

        $stripe_customer_id = $person->stripe_customer_id;
        if ($stripe_customer_id === null) {
            $this->logger->warning("tried to get tip balances for person without stripe id");
            return;
        }

        $paymentIntents = $this->stripeClient->paymentIntents->all([
            'customer' => $stripe_customer_id,
        ]);

        foreach ($paymentIntents->autoPagingIterator() as $paymentIntent) {
            if (! $this->isPaymentIntentDonorFundsTip($paymentIntent)) {
                continue;
            }

            $currencyCode = $paymentIntent->currency;

            if ($this->isPaymentIntentPendingBankTransfer($paymentIntent)) {
                if (!isset($person->pending_tip_balance[$currencyCode])) {
                    $person->pending_tip_balance[$currencyCode] = 0;
                }
                $person->pending_tip_balance[$currencyCode] += $paymentIntent->amount;
            }

            if ($this->isPaymentIntentRecentlyConfirmed($paymentIntent)) {
                if (!isset($person->recently_confirmed_tips_total[$currencyCode])) {
                    $person->recently_confirmed_tips_total[$currencyCode] = 0;
                }
                $person->recently_confirmed_tips_total[$currencyCode] += $paymentIntent->amount;
            }
        }
    }


    // Param for this and following methods is docblock only as in test the param is actually stdClass.
    /**
     * @param PaymentIntent $paymentIntent
     */
    private function isPaymentIntentRecentlyConfirmed($paymentIntent): bool
    {
        // Technically we check for recently-ish created because there's not a quick way to see if it was
        // recently confirmed.
        return $paymentIntent->status === PaymentIntent::STATUS_SUCCEEDED &&
            $paymentIntent->created > (new \DateTimeImmutable())->modify('-10 days')->getTimestamp();
    }

    /**
     * @param PaymentIntent $paymentIntent
     */
    private function isPaymentIntentPendingBankTransfer($paymentIntent): bool
    {
        return in_array(
            $paymentIntent->status,
            self::STATUSES_THAT_MAY_BE_PENDING_BANK_TRANSFER,
            true,
        );
    }

    /**
     * @param PaymentIntent $paymentIntent
     */
    private function isPaymentIntentDonorFundsTip($paymentIntent): bool
    {
        /**
         * @psalm-suppress UndefinedMagicPropertyFetch
         *
         * We could use the \ArrayAccess interface to metadata to avoid this issue, but that would
         * require changing test data substantially. Previous Stripe library versions declared types that
         * allowed this.
         */
        return (
            $paymentIntent->payment_method_types === ['customer_balance'] &&
            $paymentIntent->metadata->campaignName === self::FUND_TIPS_CAMPAIGN_NAME
        );
    }
}
