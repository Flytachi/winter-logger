<?php

declare(strict_types=1);

namespace Flytachi\Winter\Logger\Tests\Unit\Factory;

use InvalidArgumentException;
use Monolog\Formatter\JsonFormatter;
use PHPUnit\Framework\TestCase;
use Flytachi\Winter\Logger\Factory\FormatterFactory;
use Flytachi\Winter\Logger\Formatter\SpringLineFormatter;

class FormatterFactoryTest extends TestCase
{
    // ─── line ────────────────────────────────────────────────────────────────

    public function testMakeLineReturnsSpringLineFormatter(): void
    {
        $formatter = FormatterFactory::make('line');
        $this->assertInstanceOf(SpringLineFormatter::class, $formatter);
    }

    // ─── json ────────────────────────────────────────────────────────────────

    public function testMakeJsonReturnsJsonFormatter(): void
    {
        $formatter = FormatterFactory::make('json');
        $this->assertInstanceOf(JsonFormatter::class, $formatter);
    }

    // ─── unknown ─────────────────────────────────────────────────────────────

    public function testUnknownFormatThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/xml/');

        FormatterFactory::make('xml');
    }
}
