<?php

declare(strict_types=1);

namespace Flytachi\Winter\Logger\Processor;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Redacts sensitive values from log context and extra.
 * Matching is case-insensitive on keys. Nested arrays are traversed recursively.
 *
 * Add extra keys via constructor:
 *   new SensitiveMaskingProcessor(['my_secret_field'])
 */
final class SensitiveMaskingProcessor implements ProcessorInterface
{
    private const MASK = '***';

    private const DEFAULTS = [
        'password', 'passwd', 'secret', 'token', 'access_token', 'refresh_token',
        'api_key', 'apikey', 'authorization', 'auth', 'cookie', 'set-cookie',
        'credit_card', 'card_number', 'cvv', 'ssn', 'pin',
    ];

    /** @var list<string> */
    private array $keys;

    /** @param list<string> $extraKeys */
    public function __construct(array $extraKeys = [])
    {
        $this->keys = array_map(
            'strtolower',
            array_unique([...self::DEFAULTS, ...$extraKeys]),
        );
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            context: $this->redact($record->context),
            extra:   $this->redact($record->extra),
        );
    }

    private function redact(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), $this->keys, true)) {
                $out[$key] = self::MASK;
            } else {
                $out[$key] = is_array($value) ? $this->redact($value) : $value;
            }
        }
        return $out;
    }
}
