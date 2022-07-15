<?php

declare(strict_types=1);

namespace BigGive\Identity\Application\Actions;

use OpenApi\Annotations as OA;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Exception\HttpBadRequestException;

/**
 * @todo
 */
class CreatePerson extends Action
{

    #[Pure]
    public function __construct(
        private DonationRepository $donationRepository,
        private EntityManagerInterface $entityManager,
        private SerializerInterface $serializer,
        private StripeClient $stripeClient,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    /**
     * @return Response
     * @throws HttpBadRequestException
     */
    protected function action(): Response
    {
        return $this->respondWithData(['status' => 'OK']);
    }
}
