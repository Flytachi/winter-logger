<?php

declare(strict_types=1);

namespace Flytachi\Winter\Logger\Formatter;

use Monolog\Formatter\NormalizerFormatter;
use Monolog\LogRecord;

/**
 * Spring Boot-style single-line formatter.
 *
 * Output examples:
 *   [2024-01-01 12:00:00] [INFO ] [http]: User logged in {"request_id":"abc"}
 *   [2024-01-01 12:00:00] [ERROR] [UserService]: DB timeout {"class":"App\\Service\\UserService"}
 */
final class SpringLineFormatter extends NormalizerFormatter
{
    public function format(LogRecord $record): string
    {
        $datetime = $record->datetime->format('Y-m-d H:i:s');
        $level    = $this->levelLabel($record->level);
        $channel  = $record->channel;

        $tail = '';
        $data = array_merge($record->context, $record->extra);
        if (!empty($data)) {
            $tail = ' ' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return "[{$datetime}] [{$level}] [{$channel}]: {$record->message}{$tail}\n";
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
