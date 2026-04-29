<?php

declare(strict_types=1);

namespace Flytachi\Winter\Logger\Handler;

use Monolog\Handler\StreamHandler;
use Monolog\LogRecord;

/**
 * StreamHandler that silently swallows write errors instead of throwing.
 *
 * In FPM the PHP process stdout is the FastCGI response stream. If the
 * client disconnects, an fwrite() there triggers SIGPIPE and kills the
 * worker. SafeStreamHandler suppresses the error so the worker survives.
 *
 * For FPM use php://stderr (goes to FPM error_log, not the HTTP response).
 * For Swoole use php://stdout (Swoole's HTTP response is independent).
 */
final class SafeStreamHandler extends StreamHandler
{
    protected function streamWrite(mixed $stream, LogRecord $record): void
    {
        if (!is_resource($stream)) {
            return;
        }
        @fwrite($stream, $record->formatted);
    }
}
