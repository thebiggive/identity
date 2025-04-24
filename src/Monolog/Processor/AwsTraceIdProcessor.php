<?php

namespace BigGive\Identity\Monolog\Processor;

use Monolog\Processor\ProcessorInterface;

class AwsTraceIdProcessor implements ProcessorInterface
{
    public function __invoke(array|\ArrayAccess $record): array
    {
        $traceId = $_SERVER['HTTP_X_AMZN_TRACE_ID'] ?? null;
        if (is_string($traceId)) {
            $record['extra']['x-amzn-trace-id'] = $traceId;
        }

        return $record;
    }
}
