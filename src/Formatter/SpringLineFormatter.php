<?php

declare(strict_types=1);

namespace Flytachi\Winter\Logger\Formatter;

use Monolog\Formatter\NormalizerFormatter;
use Monolog\LogRecord;

/**
 * Spring Boot-style single-line formatter.
 *
 * Output examples:
 *   [2024-01-01 12:00:00] [INFO ] -http-: User logged in {"request_id":"abc"}
 *   [2024-01-01 12:00:00] [DEBUG] -http- (UserService): db query {"request_id":"abc","class":"App\\UserService"}
 *   [2024-01-01 12:00:00] [ERROR] -cli-: job failed
 */
final class SpringLineFormatter extends NormalizerFormatter
{
    public function format(LogRecord $record): string
    {
        $datetime = $record->datetime->format('Y-m-d H:i:s');
        $level    = $this->levelLabel($record->level);
        $channel  = $record->channel;

        $extra = $record->extra;
        $name  = isset($extra['class']) ? ' (' . $this->shortName((string) $extra['class']) . ')' : '';

        $data = array_merge($record->context, $extra);
        $tail = empty($data) ? '' : ' ' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return "[{$datetime}] [{$level}] -{$channel}-{$name}: {$record->message}{$tail}\n";
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
