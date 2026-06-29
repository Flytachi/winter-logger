<?php

declare(strict_types=1);

namespace Flytachi\Winter\Logger\Formatter;

use Monolog\Formatter\NormalizerFormatter;
use Monolog\LogRecord;

/**
 * Spring Boot-style single-line formatter.
 *
 * Output examples:
 *   [2024-01-01 12:00:00] [INFO ] -http- [4821]: User logged in {"request_id":"abc"}
 *   [2024-01-01 12:00:00] [DEBUG] -http- [4821] (UserService): db query {"request_id":"abc","class":"App\\UserService"}
 *   [2024-01-01 12:00:00] [ERROR] -cli- [4821]: job failed
 */
final class SpringLineFormatter extends NormalizerFormatter
{
    /**
     * @param bool $appendNewline Append a trailing newline. Keep true for file/stream
     *                            output; pass false for syslog (it frames messages itself,
     *                            so a trailing newline produces a spurious empty record).
     */
    public function __construct(private readonly bool $appendNewline = true)
    {
        parent::__construct();
    }

    public function format(LogRecord $record): string
    {
        $datetime = $record->datetime->format('Y-m-d H:i:s');
        $level    = $this->levelLabel($record->level);
        $channel  = $record->channel;

        $data = array_merge($record->context, $record->extra);
        $pid  = ' [' . getmypid() . ']';
        $name = isset($data['class']) ? ' (' . $this->shortName((string) $data['class']) . ')' : '';
        $tail = empty($data) ? '' : ' ' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $eol  = $this->appendNewline ? "\n" : '';

        return "[{$datetime}] [{$level}] -{$channel}-{$pid}{$name}: {$record->message}{$tail}{$eol}";
    }

    private function shortName(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');
        return $pos === false ? $fqcn : substr($fqcn, $pos + 1);
    }

    private function levelLabel(\Monolog\Level $level): string
    {
        return match ($level) {
            \Monolog\Level::Debug     => 'DEBUG',
            \Monolog\Level::Info      => 'INFO ',
            \Monolog\Level::Notice    => 'NOTIC',
            \Monolog\Level::Warning   => 'WARN ',
            \Monolog\Level::Error     => 'ERROR',
            \Monolog\Level::Critical  => 'CRIT ',
            \Monolog\Level::Alert     => 'ALERT',
            \Monolog\Level::Emergency => 'EMERG',
        };
    }
}
