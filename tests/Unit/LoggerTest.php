<?php

declare(strict_types=1);

namespace Flytachi\Winter\Logger\Tests\Unit;

use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger as MonologLogger;
use PHPUnit\Framework\TestCase;
use Flytachi\Winter\Logger\Context\ProcessContext;
use Flytachi\Winter\Logger\Logger;

class LoggerTest extends TestCase
{
    private TestHandler    $handler;
    private MonologLogger  $monolog;
    private ProcessContext $storage;

    protected function setUp(): void
    {
        $this->handler = new TestHandler();
        $this->monolog = new MonologLogger('test');
        $this->monolog->pushHandler($this->handler);
        $this->storage = new ProcessContext();
    }

    private function logger(array $boundContext = []): Logger
    {
        return new Logger($this->monolog, $this->storage, $boundContext);
    }

    // ─── PSR-3 methods ────────────────────────────────────────────────────────

    public function testInfoDelegatesToMonolog(): void
    {
        $this->logger()->info('hello');
        $this->assertTrue($this->handler->hasInfo('hello'));
    }

    public function testErrorDelegatesToMonolog(): void
    {
        $this->logger()->error('boom');
        $this->assertTrue($this->handler->hasError('boom'));
    }

    public function testDebugDelegatesToMonolog(): void
    {
        $this->logger()->debug('trace');
        $this->assertTrue($this->handler->hasDebug('trace'));
    }

    public function testWarningDelegatesToMonolog(): void
    {
        $this->logger()->warning('careful');
        $this->assertTrue($this->handler->hasWarning('careful'));
    }

    // ─── per-call context ────────────────────────────────────────────────────

    public function testPerCallContextPassedToMonolog(): void
    {
        $this->logger()->info('msg', ['key' => 'val']);

        $records = $this->handler->getRecords();
        $this->assertCount(1, $records);
        $this->assertSame('val', $records[0]->context['key']);
    }

    // ─── withContext ─────────────────────────────────────────────────────────

    public function testWithContextReturnsDifferentInstance(): void
    {
        $base  = $this->logger();
        $bound = $base->withContext(['a' => 1]);

        $this->assertNotSame($base, $bound);
    }

    public function testBoundContextMergedIntoEveryCall(): void
    {
        $log = $this->logger(['request_id' => 'req-1']);
        $log->info('event one');
        $log->info('event two');

        $records = $this->handler->getRecords();
        $this->assertSame('req-1', $records[0]->context['request_id']);
        $this->assertSame('req-1', $records[1]->context['request_id']);
    }

    public function testPerCallContextOverridesBoundContext(): void
    {
        $log = $this->logger(['key' => 'bound']);
        $log->info('msg', ['key' => 'override']);

        $records = $this->handler->getRecords();
        $this->assertSame('override', $records[0]->context['key']);
    }

    public function testWithContextMergesAdditivelyOnChain(): void
    {
        $log = $this->logger(['a' => 1])->withContext(['b' => 2]);
        $log->info('msg');

        $ctx = $this->handler->getRecords()[0]->context;
        $this->assertSame(1, $ctx['a']);
        $this->assertSame(2, $ctx['b']);
    }

    // ─── monolog accessor ─────────────────────────────────────────────────────

    public function testMonologReturnsUnderlyingInstance(): void
    {
        $this->assertSame($this->monolog, $this->logger()->monolog());
    }
}
