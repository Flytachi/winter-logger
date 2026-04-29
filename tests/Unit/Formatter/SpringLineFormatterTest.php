<?php

declare(strict_types=1);

namespace Flytachi\Winter\Logger\Tests\Unit\Formatter;

use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use Flytachi\Winter\Logger\Formatter\SpringLineFormatter;

class SpringLineFormatterTest extends TestCase
{
    private SpringLineFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new SpringLineFormatter();
    }

    private function makeRecord(
        string $channel = 'http',
        string $message = 'test message',
        Level  $level   = Level::Info,
        array  $context = [],
        array  $extra   = [],
    ): LogRecord {
        return new LogRecord(
            datetime: new \DateTimeImmutable('2024-01-15 12:00:00'),
            channel:  $channel,
            level:    $level,
            message:  $message,
            context:  $context,
            extra:    $extra,
        );
    }

    // ─── structure ───────────────────────────────────────────────────────────

    public function testOutputContainsDatetime(): void
    {
        $output = $this->formatter->format($this->makeRecord());
        $this->assertStringContainsString('[2024-01-15 12:00:00]', $output);
    }

    public function testOutputContainsLevel(): void
    {
        $output = $this->formatter->format($this->makeRecord(level: Level::Warning));
        $this->assertStringContainsString('[WARN ]', $output);
        $this->assertStringNotContainsString('[WARNING]', $output);
    }

    public function testOutputContainsChannel(): void
    {
        $output = $this->formatter->format($this->makeRecord(channel: 'UserService'));
        $this->assertStringContainsString('[UserService]', $output);
    }

    public function testOutputContainsMessage(): void
    {
        $output = $this->formatter->format($this->makeRecord(message: 'user created'));
        $this->assertStringContainsString('user created', $output);
    }

    public function testOutputEndsWithNewline(): void
    {
        $output = $this->formatter->format($this->makeRecord());
        $this->assertStringEndsWith("\n", $output);
    }

    // ─── level padding ───────────────────────────────────────────────────────

    public function testLevelIsPaddedToFiveChars(): void
    {
        $infoOutput  = $this->formatter->format($this->makeRecord(level: Level::Info));
        $debugOutput = $this->formatter->format($this->makeRecord(level: Level::Debug));
        $errorOutput = $this->formatter->format($this->makeRecord(level: Level::Error));

        $this->assertStringContainsString('[INFO ]', $infoOutput);
        $this->assertStringContainsString('[DEBUG]', $debugOutput);
        $this->assertStringContainsString('[ERROR]', $errorOutput);
        $this->assertStringContainsString('[WARN ]', $this->formatter->format($this->makeRecord(level: Level::Warning)));
    }

    // ─── json tail ───────────────────────────────────────────────────────────

    public function testNoJsonTailWhenNoContextAndNoExtra(): void
    {
        $output = $this->formatter->format($this->makeRecord());
        $this->assertStringEndsWith("test message\n", $output);
    }

    public function testContextAppearsInJsonTail(): void
    {
        $output = $this->formatter->format($this->makeRecord(context: ['id' => 42]));
        $this->assertStringContainsString('"id":42', $output);
    }

    public function testExtraAppearsInJsonTail(): void
    {
        $output = $this->formatter->format($this->makeRecord(extra: ['request_id' => 'abc']));
        $this->assertStringContainsString('"request_id":"abc"', $output);
    }

    public function testContextAndExtraMergedInJsonTail(): void
    {
        $output = $this->formatter->format($this->makeRecord(
            context: ['amount' => 100],
            extra:   ['request_id' => 'xyz'],
        ));
        $this->assertStringContainsString('"amount":100', $output);
        $this->assertStringContainsString('"request_id":"xyz"', $output);
    }

    // ─── full format ─────────────────────────────────────────────────────────

    public function testFullFormatStructure(): void
    {
        $output = $this->formatter->format(
            $this->makeRecord(
                channel: 'OrderService',
                message: 'order placed',
                level:   Level::Info,
                context: ['order_id' => 7],
                extra:   ['request_id' => 'req-1'],
            )
        );

        $this->assertMatchesRegularExpression(
            '/^\[2024-01-15 12:00:00\] \[INFO \] \[OrderService\]: order placed \{.*\}\n$/',
            $output,
        );
        $this->assertStringContainsString('"order_id":7', $output);
        $this->assertStringContainsString('"request_id":"req-1"', $output);
    }
}
