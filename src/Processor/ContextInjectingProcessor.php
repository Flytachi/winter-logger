<?php

declare(strict_types=1);

namespace Flytachi\Winter\Logger\Processor;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Flytachi\Winter\Logger\Contracts\ContextStorage;

/**
 * Injects runtime-scoped storage context (request_id, user_id, …) into
 * every log record's "extra" field so it appears in every line automatically.
 */
final class ContextInjectingProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly ContextStorage $storage,
    ) {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        $ctx = $this->storage->all();
        if (empty($ctx)) {
            return $record;
        }
        return $record->with(extra: array_merge($record->extra, $ctx));
    }
}
