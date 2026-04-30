<?php

declare(strict_types=1);

namespace Flytachi\Winter\Logger;

use Monolog\Logger as MonologLogger;
use Psr\Log\LoggerTrait;
use Stringable;
use Flytachi\Winter\Logger\Contracts\ContextStorage;
use Flytachi\Winter\Logger\Contracts\LoggerInterface;

/**
 * Winter logger — wraps a Monolog channel and merges per-instance bound context
 * into every log call. Runtime-scoped context (request_id, etc.) is injected
 * separately by ContextInjectingProcessor on the Monolog side.
 */
final class Logger implements LoggerInterface
{
    use LoggerTrait;

    public function __construct(
        private readonly MonologLogger $monolog,
        private readonly ContextStorage $contextStorage,
        private readonly array $boundContext = [],
    ) {
    }

    public function withContext(array $context): static
    {
        return new self(
            $this->monolog,
            $this->contextStorage,
            array_merge($this->boundContext, $context),
        );
    }

    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        $this->monolog->log(
            $level,
            (string) $message,
            empty($this->boundContext) ? $context : array_merge($this->boundContext, $context),
        );
    }

    public function monolog(): MonologLogger
    {
        return $this->monolog;
    }
}
