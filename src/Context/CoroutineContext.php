<?php

declare(strict_types=1);

namespace Flytachi\Winter\Logger\Context;

use Flytachi\Winter\Logger\Contracts\ContextStorage;

/**
 * Coroutine-scoped context for Swoole / OpenSwoole.
 *
 * Uses Swoole\Coroutine::getContext() which returns an ArrayObject bound
 * to the current coroutine. When the coroutine ends Swoole destroys its
 * context automatically — no manual cleanup required in most cases.
 *
 * Falls back to a static array when called outside a coroutine
 * (e.g. during server bootstrap or in CLI mode).
 */
final class CoroutineContext implements ContextStorage
{
    private const KEY = '__wlog__';

    private array $fallback = [];

    public function set(string $key, mixed $value): void
    {
        $bag        = &$this->bag();
        $bag[$key]  = $value;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->bag()[$key] ?? $default;
    }

    public function all(): array
    {
        return $this->bag();
    }

    public function forget(string $key): void
    {
        $bag = &$this->bag();
        unset($bag[$key]);
    }

    public function clear(): void
    {
        if ($this->inCoroutine()) {
            $ctx = \Swoole\Coroutine::getContext();
            if ($ctx !== null) {
                $ctx[self::KEY] = [];
            }
        } else {
            $this->fallback = [];
        }
    }

    private function &bag(): array
    {
        if ($this->inCoroutine()) {
            $ctx = \Swoole\Coroutine::getContext();
            if ($ctx !== null) {
                if (!isset($ctx[self::KEY])) {
                    $ctx[self::KEY] = [];
                }
                return $ctx[self::KEY];
            }
        }
        return $this->fallback;
    }

    private function inCoroutine(): bool
    {
        return class_exists(\Swoole\Coroutine::class, false)
            && \Swoole\Coroutine::getCid() > 0;
    }
}
