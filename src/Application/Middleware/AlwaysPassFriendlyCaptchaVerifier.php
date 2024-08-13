<?php

namespace BigGive\Identity\Application\Middleware;

/**
 * For use in regression test environment only
 */
class AlwaysPassFriendlyCaptchaVerifier extends FriendlyCaptchaVerifier
{
    #[\Override]
    public function verify(string $solution): true
    {
        return true;
    }
}
