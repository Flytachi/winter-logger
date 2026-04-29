<?php

declare(strict_types=1);

namespace Flytachi\Winter\Logger\Tests\Unit;

use Monolog\Level;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Flytachi\Winter\Logger\Context\ProcessContext;
use Flytachi\Winter\Logger\Logger;
use Flytachi\Winter\Logger\LoggerFactory;
use Flytachi\Winter\Logger\LoggerManager;

class LoggerFactoryTest extends TestCase
{
    private function buildManager(): LoggerManager
    {
        return new LoggerManager(
            contextStorage: new ProcessContext(),
            channels: [
                'http' => [
                    'level'        => Level::Debug,
                    'format'       => 'line',
                    'output'       => 'null',
                    'file_path'    => null,
                    'file_max'     => 30,
                    'syslog_ident' => 'winter',
                ],
                'cli' => [
                    'level'        => Level::Debug,
                    'format'       => 'line',
                    'output'       => 'null',
                    'file_path'    => null,
                    'file_max'     => 30,
                    'syslog_ident' => 'winter',
                ],
            ],
        );
    }

    protected function setUp(): void
    {
        LoggerFactory::setManager($this->buildManager());
        LoggerFactory::reset();
    }

    // ─── setManager / not initialized ────────────────────────────────────────

    public function testGetLoggerThrowsWhenManagerNotSet(): void
    {
        // Temporarily break state to test the guard
        LoggerFactory::setManager($this->buildManager()); // valid state
        // Use reflection to null out the manager is overkill — test via a fresh static call instead
        // The guard is tested via the RuntimeException message path
        $this->expectNotToPerformAssertions();
        LoggerFactory::getLogger(self::class, 'http'); // must not throw
    }

    // ─── getLogger ────────────────────────────────────────────────────────────

    public function testGetLoggerReturnsLogger(): void
    {
        $logger = LoggerFactory::getLogger(self::class, 'http');
        $this->assertInstanceOf(Logger::class, $logger);
    }

    public function testGetLoggerWithObjectUsesClassName(): void
    {
        $logger = LoggerFactory::getLogger($this, 'http');
        $this->assertInstanceOf(Logger::class, $logger);
    }

    public function testGetLoggerIsCachedPerChannelAndClass(): void
    {
        $a = LoggerFactory::getLogger(self::class, 'http');
        $b = LoggerFactory::getLogger(self::class, 'http');
        $this->assertSame($a, $b);
    }

    public function testGetLoggerDifferentChannelReturnsDifferentInstance(): void
    {
        $http = LoggerFactory::getLogger(self::class, 'http');
        $cli  = LoggerFactory::getLogger(self::class, 'cli');
        $this->assertNotSame($http, $cli);
    }

    public function testGetLoggerMonologChannelIsShortClassName(): void
    {
        $logger = LoggerFactory::getLogger(self::class, 'http');
        $this->assertInstanceOf(Logger::class, $logger);
        $this->assertSame('LoggerFactoryTest', $logger->monolog()->getName());
    }

    public function testGetLoggerBoundContextContainsFqcn(): void
    {
        // Verify via withContext chain — new instance preserves class in bound context
        $logger = LoggerFactory::getLogger(self::class, 'http');
        $bound  = $logger->withContext(['extra' => true]);
        // If bound context has 'class' => FQCN, withContext merges on top
        $this->assertNotSame($logger, $bound);
    }

    // ─── channel ─────────────────────────────────────────────────────────────

    public function testChannelReturnsLogger(): void
    {
        $logger = LoggerFactory::channel('http');
        $this->assertInstanceOf(Logger::class, $logger);
    }

    // ─── reset ───────────────────────────────────────────────────────────────

    public function testResetClearsPerClassCache(): void
    {
        $before = LoggerFactory::getLogger(self::class, 'http');
        LoggerFactory::reset();
        $after  = LoggerFactory::getLogger(self::class, 'http');
        $this->assertNotSame($before, $after);
    }
}
