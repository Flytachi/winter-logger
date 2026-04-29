<?php

declare(strict_types=1);

namespace Flytachi\Winter\Logger\Factory;

use InvalidArgumentException;
use Monolog\Handler\HandlerInterface;
use Monolog\Handler\NullHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\SyslogHandler;
use Monolog\Level;
use Flytachi\Winter\Logger\Handler\SafeStreamHandler;

final class HandlerFactory
{
    /**
     * @param array{
     *   output:    string,
     *   level:     Level,
     *   file_path: string|null,
     *   file_max:  int,
     *   syslog_ident: string,
     * } $config
     */
    public static function make(array $config): HandlerInterface
    {
        $level = $config['level'];

        return match ($config['output']) {
            'stdout'  => new SafeStreamHandler('php://stdout', $level),
            'stderr'  => new SafeStreamHandler('php://stderr', $level),
            'syslog'  => new SyslogHandler($config['syslog_ident'], LOG_USER, $level),
            'file'    => self::makeFile($config),
            'null'    => new NullHandler($level),
            default   => throw new InvalidArgumentException(
                "Unknown LOG_OUTPUT value: [{$config['output']}]. "
                . 'Allowed: stdout, stderr, syslog, file, null'
            ),
        };
    }

    private static function makeFile(array $config): HandlerInterface
    {
        $path = $config['file_path']
            ?? throw new InvalidArgumentException(
                'LOG_OUTPUT=file requires LOG_{CHANNEL}_FILE to be set'
            );

        return new RotatingFileHandler(
            filename: $path,
            maxFiles: $config['file_max'],
            level:    $config['level'],
            useLocking: true,
            dateFormat: 'Y-m-d',
        );
    }
}