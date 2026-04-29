<?php

declare(strict_types=1);

namespace Flytachi\Winter\Logger\Tests\Unit\Factory;

use InvalidArgumentException;
use Monolog\Handler\NullHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\SyslogHandler;
use Monolog\Level;
use PHPUnit\Framework\TestCase;
use Flytachi\Winter\Logger\Factory\HandlerFactory;
use Flytachi\Winter\Logger\Handler\SafeStreamHandler;

class HandlerFactoryTest extends TestCase
{
    private function baseConfig(string $output, ?string $filePath = null): array
    {
        return [
            'output'       => $output,
            'level'        => Level::Debug,
            'file_path'    => $filePath,
            'file_max'     => 30,
            'syslog_ident' => 'winter',
        ];
    }

    // ─── stdout / stderr ──────────────────────────────────────────────────────

    public function testStdoutReturnsSafeStreamHandler(): void
    {
        $handler = HandlerFactory::make($this->baseConfig('stdout'));
        $this->assertInstanceOf(SafeStreamHandler::class, $handler);
    }

    public function testStderrReturnsSafeStreamHandler(): void
    {
        $handler = HandlerFactory::make($this->baseConfig('stderr'));
        $this->assertInstanceOf(SafeStreamHandler::class, $handler);
    }

    // ─── syslog ───────────────────────────────────────────────────────────────

    public function testSyslogReturnsSyslogHandler(): void
    {
        $handler = HandlerFactory::make($this->baseConfig('syslog'));
        $this->assertInstanceOf(SyslogHandler::class, $handler);
    }

    // ─── null ─────────────────────────────────────────────────────────────────

    public function testNullReturnsNullHandler(): void
    {
        $handler = HandlerFactory::make($this->baseConfig('null'));
        $this->assertInstanceOf(NullHandler::class, $handler);
    }

    // ─── file ─────────────────────────────────────────────────────────────────

    public function testFileReturnsRotatingFileHandler(): void
    {
        $path    = sys_get_temp_dir() . '/wlog-test-' . uniqid() . '.log';
        $handler = HandlerFactory::make($this->baseConfig('file', $path));
        $this->assertInstanceOf(RotatingFileHandler::class, $handler);
    }

    public function testFileWithoutPathThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        HandlerFactory::make($this->baseConfig('file', null));
    }

    // ─── unknown ──────────────────────────────────────────────────────────────

    public function testUnknownOutputThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/kafka/');
        HandlerFactory::make($this->baseConfig('kafka'));
    }
}
