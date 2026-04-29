<?php

declare(strict_types=1);

namespace Flytachi\Winter\Logger\Contracts;

interface ContextStorage
{
    public function set(string $key, mixed $value): void;

    public function get(string $key, mixed $default = null): mixed;

    public function all(): array;

    public function forget(string $key): void;

    /**
     * Clear all context for the current execution unit.
     * Call at the end of every request / job / coroutine.
     */
    public function clear(): void;
}