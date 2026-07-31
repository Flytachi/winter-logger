<?php

declare(strict_types=1);

namespace Flytachi\Winter\Logger\Factory;

use InvalidArgumentException;
use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\JsonFormatter;
use Flytachi\Winter\Logger\Formatter\SpringLineFormatter;

final class FormatterFactory
{
    /**
     * @param string $format        'line' | 'json'
     * @param bool   $appendNewline Append a trailing newline. Pass false for syslog,
     *                              which frames messages itself.
     * @param bool   $color         Colour the line output (ANSI). Ignored for JSON.
     */
    public static function make(string $format, bool $appendNewline = true, bool $color = false): FormatterInterface
    {
        return match (strtolower($format)) {
            'line'  => new SpringLineFormatter($appendNewline, $color),
            'json'  => new JsonFormatter(
                batchMode: JsonFormatter::BATCH_MODE_NEWLINES,
                appendNewline: $appendNewline,
                ignoreEmptyContextAndExtra: false,
                includeStacktraces: true,
            ),
            default => throw new InvalidArgumentException("Unknown log format: [{$format}]"),
        };
    }
}
