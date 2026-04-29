<?php

declare(strict_types=1);

namespace Flytachi\Winter\Logger;

use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Java-style static logger factory.
 *
 * Bootstrap (once at application start):
 *   LoggerFactory::setManager($manager);
 *
 * Usage anywhere in the code:
 *   LoggerFactory::getLogger(UserService::class, 'http')->info('user created');
 *   LoggerFactory::getLogger($this, 'cli')->warning('job slow');
 *   LoggerFactory::channel('http')->info('raw channel');
 *
 * The class name becomes the Monolog channel name in log output so you can
 * filter by class in Kibana / Loki / grep without changing the log format.
 * The full FQCN is stored in context['class'] for exact lookup.
 */
final class LoggerFactory
{
    private static ?LoggerManager $manager = null;

    /** @var array<string, LoggerInterface> keyed by "channel:ClassName" */
    private static array $cache = [];

    private function __construct() {}

    public static function setManager(LoggerManager $manager): void
    {
        self::$manager = $manager;
        self::$cache   = [];
    }

    /**
     * Get a logger named after a class on the given channel.
     *
     * @param string|object $class  FQCN, ::class constant, or $this
     * @param string        $channel  'http' | 'cli' | any custom channel
     */
    public static function getLogger(string|object $class, string $channel): LoggerInterface
    {
        $fqcn     = is_object($class) ? $class::class : $class;
        $cacheKey = $channel . ':' . $fqcn;

        return self::$cache[$cacheKey] ??= self::resolve($fqcn, $channel);
    }

    /**
     * Get the raw channel logger (no per-class naming).
     */
    public static function channel(string $name): LoggerInterface
    {
        return self::manager()->channel($name);
    }

    /**
     * Reset per-class cache. Useful in daemons after config reload or in tests.
     */
    public static function reset(): void
    {
        self::$cache = [];
    }

    private static function resolve(string $fqcn, string $channel): LoggerInterface
    {
        $manager = self::manager();
        $base    = $manager->channel($channel);

        if (!($base instanceof Logger)) {
            // Monolog not installed — NullLogger or custom PSR-3 impl, return as-is
            return $base;
        }

        // Give this logger the short class name as Monolog channel so it shows
        // in log output; keep the full FQCN in bound context for searchability.
        $monolog = $base->monolog()->withName(self::shortName($fqcn));

        return new Logger(
            monolog: $monolog,
            contextStorage: $manager->contextStorage(),
            boundContext: ['class' => $fqcn],
        );
    }

    private static function shortName(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');
        return $pos === false ? $fqcn : substr($fqcn, $pos + 1);
    }

    private static function manager(): LoggerManager
    {
        return self::$manager
            ?? throw new RuntimeException(
                'Winter\Logger\LoggerFactory is not initialized. '
                . 'Call LoggerFactory::setManager($manager) at application bootstrap.'
            );
    }
}
