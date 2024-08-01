<?php

declare(strict_types=1);

namespace BigGive\Identity\Application\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpUnauthorizedException;

/**
 * Easily return well-formatted 401s
 */
trait ErrorTrait
{
    protected function unauthorised(LoggerInterface $logger, bool $likelyBot, ServerRequestInterface $request): never
    {
        if ($likelyBot) {
            // We've seen traffic with no JWTs from crawlers etc. before so don't
            // want to log this as a warning.
            $logger->info('Unauthorised – following bot-like patterns');
        } else {
            $logger->warning('Unauthorised');
        }

        throw new HttpUnauthorizedException($request, 'Unauthorised');
    }
}
