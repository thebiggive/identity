<?php

namespace BigGive\Identity\Tests;

use Psr\Log\AbstractLogger;

class TestLogger extends AbstractLogger
{
    /** @var list<array{level: mixed, message: string, context: array}>  */
    public array $messages = [];

    public function log($level, \Stringable|string $message, array $context = []): void
    {
        $this->messages[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
    }
}
