<?php

declare(strict_types=1);

namespace Flytachi\Winter\Logger\Tests\Unit\Context;

use PHPUnit\Framework\TestCase;
use Flytachi\Winter\Logger\Context\ProcessContext;

class ProcessContextTest extends TestCase
{
    private ProcessContext $ctx;

    protected function setUp(): void
    {
        $this->ctx = new ProcessContext();
    }

    // ─── set / get ───────────────────────────────────────────────────────────

    public function testSetAndGet(): void
    {
        $this->ctx->set('request_id', 'abc');
        $this->assertSame('abc', $this->ctx->get('request_id'));
    }

    public function testGetReturnsDefaultWhenKeyMissing(): void
    {
        $this->assertNull($this->ctx->get('missing'));
        $this->assertSame('fallback', $this->ctx->get('missing', 'fallback'));
    }

    public function testSetOverwritesExistingKey(): void
    {
        $this->ctx->set('key', 'first');
        $this->ctx->set('key', 'second');
        $this->assertSame('second', $this->ctx->get('key'));
    }

    public function testSetAcceptsMixedTypes(): void
    {
        $this->ctx->set('int',   42);
        $this->ctx->set('bool',  true);
        $this->ctx->set('null',  null);
        $this->ctx->set('array', ['a', 'b']);

        $this->assertSame(42,          $this->ctx->get('int'));
        $this->assertTrue($this->ctx->get('bool'));
        $this->assertNull($this->ctx->get('null'));
        $this->assertSame(['a', 'b'],  $this->ctx->get('array'));
    }

    // ─── all ─────────────────────────────────────────────────────────────────

    public function testAllReturnsEmptyArrayInitially(): void
    {
        $this->assertSame([], $this->ctx->all());
    }

    public function testAllReturnsAllSetValues(): void
    {
        $this->ctx->set('a', 1);
        $this->ctx->set('b', 2);
        $this->assertSame(['a' => 1, 'b' => 2], $this->ctx->all());
    }

    // ─── forget ──────────────────────────────────────────────────────────────

    public function testForgetRemovesKey(): void
    {
        $this->ctx->set('x', 'value');
        $this->ctx->forget('x');
        $this->assertNull($this->ctx->get('x'));
        $this->assertArrayNotHasKey('x', $this->ctx->all());
    }

    public function testForgetNonExistentKeyDoesNotThrow(): void
    {
        $this->ctx->forget('ghost');
        $this->assertSame([], $this->ctx->all());
    }

    // ─── clear ───────────────────────────────────────────────────────────────

    public function testClearEmptiesAllData(): void
    {
        $this->ctx->set('a', 1);
        $this->ctx->set('b', 2);
        $this->ctx->clear();
        $this->assertSame([], $this->ctx->all());
    }

    public function testClearOnEmptyContextDoesNotThrow(): void
    {
        $this->ctx->clear();
        $this->assertSame([], $this->ctx->all());
    }

    public function testSetAfterClearWorks(): void
    {
        $this->ctx->set('a', 1);
        $this->ctx->clear();
        $this->ctx->set('b', 2);
        $this->assertSame(['b' => 2], $this->ctx->all());
    }
}
