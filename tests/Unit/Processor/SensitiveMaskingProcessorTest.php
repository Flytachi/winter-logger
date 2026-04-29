<?php

declare(strict_types=1);

namespace Flytachi\Winter\Logger\Tests\Unit\Processor;

use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use Flytachi\Winter\Logger\Processor\SensitiveMaskingProcessor;

class SensitiveMaskingProcessorTest extends TestCase
{
    private const MASK = '***';

    private function makeRecord(array $context = [], array $extra = []): LogRecord
    {
        return new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel:  'test',
            level:    Level::Info,
            message:  'test',
            context:  $context,
            extra:    $extra,
        );
    }

    // ─── default keys ─────────────────────────────────────────────────────────

    public function testMasksPasswordInContext(): void
    {
        $processor = new SensitiveMaskingProcessor();
        $result    = ($processor)($this->makeRecord(['password' => 'hunter2']));

        $this->assertSame(self::MASK, $result->context['password']);
    }

    public function testMasksTokenInExtra(): void
    {
        $processor = new SensitiveMaskingProcessor();
        $result    = ($processor)($this->makeRecord(extra: ['token' => 'secret-jwt']));

        $this->assertSame(self::MASK, $result->extra['token']);
    }

    public function testMasksMultipleDefaultKeys(): void
    {
        $processor = new SensitiveMaskingProcessor();
        $result    = ($processor)($this->makeRecord([
            'password'  => 'pass',
            'api_key'   => 'key',
            'secret'    => 'shh',
            'safe_field' => 'visible',
        ]));

        $this->assertSame(self::MASK, $result->context['password']);
        $this->assertSame(self::MASK, $result->context['api_key']);
        $this->assertSame(self::MASK, $result->context['secret']);
        $this->assertSame('visible',  $result->context['safe_field']);
    }

    // ─── case-insensitive ─────────────────────────────────────────────────────

    public function testMatchingIsCaseInsensitive(): void
    {
        $processor = new SensitiveMaskingProcessor();
        $result    = ($processor)($this->makeRecord([
            'Password'  => 'a',
            'PASSWORD'  => 'b',
            'pAsSwOrD'  => 'c',
        ]));

        $this->assertSame(self::MASK, $result->context['Password']);
        $this->assertSame(self::MASK, $result->context['PASSWORD']);
        $this->assertSame(self::MASK, $result->context['pAsSwOrD']);
    }

    // ─── nested arrays ────────────────────────────────────────────────────────

    public function testMasksNestedSensitiveKeys(): void
    {
        $processor = new SensitiveMaskingProcessor();
        $result    = ($processor)($this->makeRecord([
            'user' => [
                'name'     => 'Alice',
                'password' => 'secret',
            ],
        ]));

        $this->assertSame('Alice',    $result->context['user']['name']);
        $this->assertSame(self::MASK, $result->context['user']['password']);
    }

    // ─── custom keys ─────────────────────────────────────────────────────────

    public function testCustomKeysAreMasked(): void
    {
        $processor = new SensitiveMaskingProcessor(['patient_id', 'ssn_hash']);
        $result    = ($processor)($this->makeRecord([
            'patient_id' => 99,
            'ssn_hash'   => 'abc',
            'name'       => 'Bob',
        ]));

        $this->assertSame(self::MASK, $result->context['patient_id']);
        $this->assertSame(self::MASK, $result->context['ssn_hash']);
        $this->assertSame('Bob',      $result->context['name']);
    }

    // ─── passthrough ─────────────────────────────────────────────────────────

    public function testNonSensitiveValuesArePassedThrough(): void
    {
        $processor = new SensitiveMaskingProcessor();
        $result    = ($processor)($this->makeRecord([
            'user_id' => 42,
            'email'   => 'alice@example.com',
            'status'  => 'active',
        ]));

        $this->assertSame(42,                  $result->context['user_id']);
        $this->assertSame('alice@example.com', $result->context['email']);
        $this->assertSame('active',            $result->context['status']);
    }

    public function testEmptyContextPassesThroughUnchanged(): void
    {
        $processor = new SensitiveMaskingProcessor();
        $result    = ($processor)($this->makeRecord());

        $this->assertSame([], $result->context);
        $this->assertSame([], $result->extra);
    }
}
