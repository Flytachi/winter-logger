<?php

declare(strict_types=1);

namespace Flytachi\Winter\Logger\Contracts;

/**
 * Per-unit-of-work store for the fields every log record should carry.
 *
 * One implementation keeps them in a coroutine, another in the process — which is what
 * lets the same logging code stay correct under Swoole and under FPM. Whoever drives the
 * unit of work is responsible for calling clear() when it ends.
 *
 * @link https://winterframe.net/packages/logger/context-isolation The two implementations and when each applies
 */
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
