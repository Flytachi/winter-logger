<?php

declare(strict_types=1);

namespace Flytachi\Winter\Logger\Contracts;

use Psr\Log\LoggerInterface as PsrLoggerInterface;

/**
 * PSR-3 logger plus withContext() — the contract this package implements.
 *
 * The addition is deliberately small: everything else is standard PSR-3, so application
 * code can type-hint Psr\Log\LoggerInterface and stay portable.
 *
 * @link https://winterframe.net/packages/logger/api-reference#logger withContext() and the level methods
 */
interface LoggerInterface extends PsrLoggerInterface
{
    /**
     * Return a new instance with the given context merged into every log call.
     * Does not mutate the current instance.
     */
    public function withContext(array $context): static;
}
