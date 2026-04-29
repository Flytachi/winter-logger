<?php

declare(strict_types=1);

namespace Flytachi\Winter\Logger\Context;

use Flytachi\Winter\Logger\Contracts\ContextStorage;

/**
 * Process-scoped context. One array per process lifetime.
 * Safe for FPM (one request = one process) and CLI jobs.
 * NOT safe for Swoole — coroutines share the same process, use CoroutineContext there.
 */
final class ProcessContext implements ContextStorage
{
    private array $data = [];

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function all(): array
    {
        return $this->data;
    }

    public function forget(string $key): void
    {
        unset($this->data[$key]);
    }

    public function clear(): void
    {
        $this->data = [];
    }
}