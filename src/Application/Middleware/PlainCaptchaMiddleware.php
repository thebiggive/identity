<?php

namespace BigGive\Identity\Application\Middleware;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Verifies a captcha code sent in an `x-captcha-code` custom header
 */
class PlainCaptchaMiddleware extends CaptchaMiddleware
{
    protected function getCode(ServerRequestInterface $request): ?string
    {
        $captchaHeaders = $request->getHeader('x-captcha-code');

        return reset($captchaHeaders);
    }
}
