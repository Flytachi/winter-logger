<?php

declare(strict_types=1);

namespace Flytachi\Winter\Logger;

/**
 * Static logging facade — quick shortcut to the default channel.
 *
 * Equivalent to LoggerFactory::logger()->{level}($message, $context).
 * The default channel is set by the framework kernel (e.g. 'http' for FPM/Swoole, 'cli' for CLI).
 *
 * Usage:
 *   Log::info('user created', ['id' => $id]);
 *   Log::error('payment failed', ['order' => $orderId]);
 *   Log::debug('cache miss');
 */
final class Log
{
    private function __construct()
    {
    }

    public static function debug(string $message, array $context = []): void
    {
        LoggerFactory::logger()->debug($message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        LoggerFactory::logger()->info($message, $context);
    }

    public static function notice(string $message, array $context = []): void
    {
        LoggerFactory::logger()->notice($message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        LoggerFactory::logger()->warning($message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        LoggerFactory::logger()->error($message, $context);
    }

    public static function critical(string $message, array $context = []): void
    {
        LoggerFactory::logger()->critical($message, $context);
    }

    public static function alert(string $message, array $context = []): void
    {
        LoggerFactory::logger()->alert($message, $context);
    }
}
