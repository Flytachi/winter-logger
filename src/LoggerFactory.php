<?php

declare(strict_types=1);

namespace Flytachi\Winter\Logger;

use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Java-style static logger factory.
 *
 * Bootstrap (once at application start, done by the framework):
 *   LoggerFactory::setManager($manager);
 *   LoggerFactory::setDefaultChannel('http'); // or 'cli'
 *
 * Usage anywhere in application code:
 *   LoggerFactory::getLogger(UserService::class)->info('user created');
 *   LoggerFactory::getLogger($this)->warning('slow');
 *   LoggerFactory::getLogger(MyJob::class, 'cli')->debug('override channel');
 *   LoggerFactory::channel('http')->info('raw channel');
 *
 * The class name becomes the Monolog channel name in log output so you can
 * filter by class in Kibana / Loki / grep without changing the log format.
 * The full FQCN is stored in context['class'] for exact lookup.
 */
final class LoggerFactory
{
    private static ?LoggerManager $manager = null;

    private static string $defaultChannel = 'cli';

    /** @var array<string, LoggerInterface> keyed by "channel:ClassName" */
    private static array $cache = [];

    private function __construct()
    {
    }

    public static function setManager(LoggerManager $manager): void
    {
        self::$manager = $manager;
        self::$cache   = [];
    }

    /**
     * Add (or override) a single channel on the current manager.
     * Called from bootstrap or entry point after Kernel::init():
     *   LoggerFactory::addChannel('job', Kernel::channelConfig('job'));
     */
    public static function addChannel(string $name, array $config): void
    {
        self::$manager = self::manager()->withChannel($name, $config);
        self::$cache   = [];
    }

    /**
     * Swap the context storage on the current manager (same channels, new storage).
     * Called by the entry point when the runtime is known — e.g. Swoole server runner
     * calls setContextStorage(new CoroutineContext()) before accepting requests.
     */
    public static function setContextStorage(\Flytachi\Winter\Logger\Contracts\ContextStorage $storage): void
    {
        self::$manager = self::manager()->withContextStorage($storage);
        self::$cache   = [];
    }

    /**
     * Set the default channel used by getLogger() when no channel is specified.
     * Called by the entry point: public/index.php → 'http', swoole runner → 'http', cli → 'cli'.
     */
    public static function setDefaultChannel(string $channel): void
    {
        self::$defaultChannel = $channel;
        self::$cache          = [];
    }

    /**
     * Get a logger named after a class.
     *
     * Uses the default channel set by the kernel unless overridden explicitly.
     *
     * @param string|object $class    FQCN, ::class constant, or $this
     * @param string|null   $channel  Override channel; null = use default set by kernel
     */
    public static function getLogger(string|object $class, ?string $channel = null): LoggerInterface
    {
        $fqcn = is_object($class) ? $class::class : $class;
        $ch = ($channel !== null && self::manager()->hasChannel($channel))
            ? $channel
            : self::$defaultChannel;

        return self::$cache[$ch . ':' . $fqcn] ??= self::resolve($fqcn, $ch);
    }

    /**
     * Get the raw channel logger (no per-class naming).
     */
    public static function channel(string $name): LoggerInterface
    {
        return self::manager()->channel($name);
    }

    /**
     * Get the default channel logger. Used by the Log facade.
     */
    public static function logger(): LoggerInterface
    {
        return self::manager()->channel(self::$defaultChannel);
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

        return new Logger(
            monolog: $base->monolog(),
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
