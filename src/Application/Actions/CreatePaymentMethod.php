<?php

declare(strict_types=1);

namespace BigGive\Identity\Application\Actions;

use Psr\Http\Message\ResponseInterface as Response;
use Slim\Exception\HttpBadRequestException;

/**
 * @todo
 */
class CreatePaymentMethod extends Action
{
    /**
     * @return Response
     * @throws HttpBadRequestException
     */
    protected function action(): Response
    {
        return $this->respondWithData(['status' => 'OK']);
    }
}
