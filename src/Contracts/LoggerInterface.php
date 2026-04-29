<?php

declare(strict_types=1);

namespace Flytachi\Winter\Logger\Contracts;

use Psr\Log\LoggerInterface as PsrLoggerInterface;

interface LoggerInterface extends PsrLoggerInterface
{
    /**
     * Return a new instance with the given context merged into every log call.
     * Does not mutate the current instance.
     */
    public function withContext(array $context): static;
}