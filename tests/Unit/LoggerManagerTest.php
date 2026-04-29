<?php

declare(strict_types=1);

namespace Flytachi\Winter\Logger\Tests\Unit;

use InvalidArgumentException;
use Monolog\Level;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Flytachi\Winter\Logger\Context\ProcessContext;
use Flytachi\Winter\Logger\Logger;
use Flytachi\Winter\Logger\LoggerManager;

class LoggerManagerTest extends TestCase
{
    private function manager(array $channels = []): LoggerManager
    {
        return new LoggerManager(
            contextStorage: new ProcessContext(),
            channels: $channels ?: $this->defaultChannels(),
        );
    }

    private function defaultChannels(): array
    {
        return [
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
                'format'       => 'json',
                'output'       => 'null',
                'file_path'    => null,
                'file_max'     => 30,
                'syslog_ident' => 'winter',
            ],
        ];
    }

    // ─── channel building ─────────────────────────────────────────────────────

    public function testChannelReturnsLogger(): void
    {
        $logger = $this->manager()->channel('http');
        $this->assertInstanceOf(Logger::class, $logger);
    }

    public function testChannelIsCached(): void
    {
        $manager = $this->manager();
        $a = $manager->channel('http');
        $b = $manager->channel('http');
        $this->assertSame($a, $b);
    }

    public function testDifferentChannelsReturnDifferentInstances(): void
    {
        $manager = $this->manager();
        $this->assertNotSame(
            $manager->channel('http'),
            $manager->channel('cli'),
        );
    }

    public function testUndefinedChannelThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/unknown/');

        $this->manager()->channel('unknown');
    }

    // ─── flush ────────────────────────────────────────────────────────────────

    public function testFlushResetsChannelCache(): void
    {
        $manager = $this->manager();
        $before  = $manager->channel('http');
        $manager->flush();
        $after   = $manager->channel('http');

        $this->assertNotSame($before, $after);
    }

    // ─── contextStorage ──────────────────────────────────────────────────────

    public function testContextStorageReturnsInjectedInstance(): void
    {
        $storage = new ProcessContext();
        $manager = new LoggerManager($storage, $this->defaultChannels());

        $this->assertSame($storage, $manager->contextStorage());
    }
}
