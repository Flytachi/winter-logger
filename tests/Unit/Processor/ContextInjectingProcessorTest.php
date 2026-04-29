<?php

declare(strict_types=1);

namespace Flytachi\Winter\Logger\Tests\Unit\Processor;

use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use Flytachi\Winter\Logger\Context\ProcessContext;
use Flytachi\Winter\Logger\Processor\ContextInjectingProcessor;

class ContextInjectingProcessorTest extends TestCase
{
    private function makeRecord(array $extra = []): LogRecord
    {
        return new LogRecord(
            datetime:  new \DateTimeImmutable(),
            channel:   'test',
            level:     Level::Info,
            message:   'test message',
            context:   [],
            extra:     $extra,
        );
    }

    // ─── empty context early-exit ─────────────────────────────────────────────

    public function testReturnsRecordUnchangedWhenContextEmpty(): void
    {
        $storage   = new ProcessContext();
        $processor = new ContextInjectingProcessor($storage);
        $record    = $this->makeRecord();

        $result = ($processor)($record);

        $this->assertSame($record, $result);
    }

    // ─── injection ────────────────────────────────────────────────────────────

    public function testInjectsStorageContextIntoExtra(): void
    {
        $storage = new ProcessContext();
        $storage->set('request_id', 'abc-123');
        $storage->set('user_id',    42);

        $processor = new ContextInjectingProcessor($storage);
        $result    = ($processor)($this->makeRecord());

        $this->assertSame('abc-123', $result->extra['request_id']);
        $this->assertSame(42,        $result->extra['user_id']);
    }

    public function testMergesWithExistingExtra(): void
    {
        $storage = new ProcessContext();
        $storage->set('request_id', 'xyz');

        $processor = new ContextInjectingProcessor($storage);
        $record    = $this->makeRecord(['existing' => 'value']);
        $result    = ($processor)($record);

        $this->assertSame('value', $result->extra['existing']);
        $this->assertSame('xyz',   $result->extra['request_id']);
    }

    public function testStorageContextOverridesExistingExtraOnSameKey(): void
    {
        $storage = new ProcessContext();
        $storage->set('key', 'from-storage');

        $processor = new ContextInjectingProcessor($storage);
        $record    = $this->makeRecord(['key' => 'from-record']);
        $result    = ($processor)($record);

        $this->assertSame('from-storage', $result->extra['key']);
    }

    // ─── does not touch context field ─────────────────────────────────────────

    public function testDoesNotModifyContextField(): void
    {
        $storage = new ProcessContext();
        $storage->set('request_id', 'abc');

        $processor = new ContextInjectingProcessor($storage);
        $record    = $this->makeRecord();
        $result    = ($processor)($record);

        $this->assertSame([], $result->context);
    }
}
