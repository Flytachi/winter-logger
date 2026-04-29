<?php

declare(strict_types=1);

namespace Flytachi\Winter\Logger;

use InvalidArgumentException;
use Monolog\Logger as MonologLogger;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Flytachi\Winter\Logger\Contracts\ContextStorage;
use Flytachi\Winter\Logger\Factory\FormatterFactory;
use Flytachi\Winter\Logger\Factory\HandlerFactory;
use Flytachi\Winter\Logger\Processor\ContextInjectingProcessor;

/**
 * Builds and caches channel loggers from a pre-built config array.
 *
 * Config is provided by the framework (e.g. winter-kernel) which is
 * responsible for reading env vars, detecting runtime, and resolving paths.
 * This class knows nothing about env or infrastructure.
 *
 * Channel config shape:
 * [
 *   'level'        => \Monolog\Level,
 *   'format'       => 'line' | 'json',
 *   'output'       => 'stdout' | 'stderr' | 'syslog' | 'file' | 'null',
 *   'file_path'    => string|null,   // required when output=file
 *   'file_max'     => int,           // default 30
 *   'syslog_ident' => string,        // default 'winter'
 * ]
 */
final class LoggerManager
{
    /** @var array<string, LoggerInterface> Built and cached channel instances */
    private array $cache = [];

    /**
     * @param array<string, array> $channels  Channel configs keyed by channel name
     */
    public function __construct(
        private readonly ContextStorage $contextStorage,
        private readonly array $channels = [],
    ) {}

    public function channel(string $name): LoggerInterface
    {
        return $this->cache[$name] ??= $this->build($name);
    }

    public function contextStorage(): ContextStorage
    {
        return $this->contextStorage;
    }

    /**
     * Drop all cached channel instances. Useful after config changes in a daemon.
     */
    public function flush(): void
    {
        $this->cache = [];
    }

    private function build(string $channel): LoggerInterface
    {
        if (!class_exists(MonologLogger::class)) {
            return new NullLogger();
        }

        $config = $this->channels[$channel]
            ?? throw new InvalidArgumentException("Logger channel [{$channel}] is not configured.");

        $monolog = new MonologLogger($channel);
        $monolog->pushProcessor(new ContextInjectingProcessor($this->contextStorage));

        $handler = HandlerFactory::make($config);
        if (method_exists($handler, 'setFormatter')) {
            $handler->setFormatter(FormatterFactory::make($config['format'] ?? 'line'));
        }
        $monolog->pushHandler($handler);

        return new Logger($monolog, $this->contextStorage);
    }
}
