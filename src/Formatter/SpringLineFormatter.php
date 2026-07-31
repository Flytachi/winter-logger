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
    /**
     * @param bool $appendNewline Append a trailing newline (see above).
     * @param bool $color Wrap segments in ANSI colour (level, channel, class, dimmed
     *                    timestamp/pid/context). Line format only — never for JSON.
     */
    public function __construct(
        private readonly bool $appendNewline = true,
        private readonly bool $color = false,
    ) {
        parent::__construct();
    }

    public function format(LogRecord $record): string
    {
        $datetime = $record->datetime->format('Y-m-d H:i:s');
        $level    = $this->levelLabel($record->level);
        $channel  = $record->channel;

        $data = array_merge($record->context, $record->extra);
        $pid  = (string) getmypid();
        $name = isset($data['class']) ? $this->shortName((string) $data['class']) : null;
        $tail = empty($data) ? '' : json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $eol  = $this->appendNewline ? "\n" : '';

        if (!$this->color) {
            $namePart = $name !== null ? " ({$name})" : '';
            $tailPart = $tail === '' ? '' : " {$tail}";
            return "[{$datetime}] [{$level}] -{$channel}- [{$pid}]{$namePart}: {$record->message}{$tailPart}{$eol}";
        }

        // Variant B — dim timestamp/pid/context, coloured level, cyan channel,
        // magenta class name.
        $r        = "\033[0m";
        $dim      = "\033[90m";
        $lvl      = $this->levelColor($record->level);
        $namePart = $name !== null ? " \033[35m({$name}){$r}" : '';
        $tailPart = $tail === '' ? '' : " {$dim}{$tail}{$r}";

        return "{$dim}[{$datetime}]{$r} {$lvl}[{$level}]{$r} \033[36m-{$channel}-{$r}"
            . " {$dim}[{$pid}]{$r}{$namePart}: {$record->message}{$tailPart}{$eol}";
    }

    /** ANSI colour for the level token (Variant B palette). */
    private function levelColor(\Monolog\Level $level): string
    {
        return match ($level) {
            \Monolog\Level::Debug     => "\033[90m",    // grey
            \Monolog\Level::Info      => "\033[32m",    // green
            \Monolog\Level::Notice    => "\033[36m",    // cyan
            \Monolog\Level::Warning   => "\033[33m",    // yellow
            \Monolog\Level::Error     => "\033[31m",    // red
            \Monolog\Level::Critical  => "\033[91m",    // bright red
            \Monolog\Level::Alert     => "\033[1;91m",  // bold bright red
            \Monolog\Level::Emergency => "\033[97;41m", // white on red
        };
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
