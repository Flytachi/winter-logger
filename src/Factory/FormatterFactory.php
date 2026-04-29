<?php

declare(strict_types=1);

namespace Flytachi\Winter\Logger\Factory;

use InvalidArgumentException;
use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\JsonFormatter;
use Flytachi\Winter\Logger\Formatter\SpringLineFormatter;

final class FormatterFactory
{
    public static function make(string $format): FormatterInterface
    {
        return match (strtolower($format)) {
            'line'  => new SpringLineFormatter(),
            'json'  => new JsonFormatter(
                batchMode: JsonFormatter::BATCH_MODE_NEWLINES,
                appendNewline: true,
                ignoreEmptyContextAndExtra: false,
                includeStacktraces: true,
            ),
            default => throw new InvalidArgumentException("Unknown log format: [{$format}]"),
        };
    }
}