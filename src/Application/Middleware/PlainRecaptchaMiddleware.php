<?php

namespace BigGive\Identity\Application\Middleware;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Verifies a captcha code sent in an `x-captcha-code` custom header
 */
class PlainRecaptchaMiddleware extends RecaptchaMiddleware
{
    protected function getCode(ServerRequestInterface $request): ?string
    {
        $captchaHeaders = $request->getHeader('x-captcha-code');

        return reset($captchaHeaders);
    }

    protected function isUsingFriendlyCaptcha(ServerRequestInterface $request): true
    {
        return true;
    }
}
