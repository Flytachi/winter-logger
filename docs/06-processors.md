# Processors

Processors modify Monolog `LogRecord` objects before they reach a handler.
They are added to the Monolog instance in the order they should run.

---

## `ContextInjectingProcessor` *(automatic)*

Merges the current `ContextStorage` snapshot into every record's `extra` field.
This is how `request_id`, `user_id`, and similar fields appear in every log
line without being passed manually.

**Added automatically** to every channel by `LoggerManager`. No configuration needed.

```php
// You set context once:
$manager->contextStorage()->set('request_id', 'abc-123');
$manager->contextStorage()->set('user_id',    42);

// Every log call in this request carries those fields in extra:
$logger->info('payment processed', ['amount' => 100]);
// → extra: {"request_id":"abc-123","user_id":42}
// → context: {"amount":100}
```

**Early-exit optimisation:** if `ContextStorage::all()` returns an empty array
the processor returns the record unchanged — zero overhead when no context is set.

---

## `SensitiveMaskingProcessor` *(optional)*

Redacts sensitive values before they reach any handler. Matching is
**case-insensitive** on keys, and nested arrays are traversed recursively.

The scope is the call's `context`. Fields coming from the context storage are
**not** covered by default — see [Processor order](#processor-order) below.

```php
use Flytachi\Winter\Logger\Processor\SensitiveMaskingProcessor;

// Default keys only:
$processor = new SensitiveMaskingProcessor();

// With extra keys:
$processor = new SensitiveMaskingProcessor(['patient_id', 'insurance_number']);

// Add to a channel after building the manager:
$manager->channel('http')->monolog()->pushProcessor($processor);
```

### Default masked keys

`password`, `passwd`, `secret`, `token`, `access_token`, `refresh_token`,
`api_key`, `apikey`, `authorization`, `auth`, `cookie`, `set-cookie`,
`credit_card`, `card_number`, `cvv`, `ssn`, `pin`

All are replaced with `***`.

### Example

```php
$logger->info('login attempt', [
    'username' => 'alice',
    'password' => 'hunter2',     // ← will be masked
    'metadata' => [
        'token' => 'secret-jwt', // ← nested, also masked
    ],
]);
```

Log output:

```
[2024-01-01 12:00:00] [INFO ] -http- [4821]: login attempt {"username":"alice","password":"***","metadata":{"token":"***"}}
```

### Processor order

Monolog runs its processors top to bottom, and `pushProcessor()` **prepends**. The
manager already pushed `ContextInjectingProcessor` while building the channel, so a
masking processor added afterwards lands ahead of it:

```
0. SensitiveMaskingProcessor   ← runs first: extra is still empty
1. ContextInjectingProcessor   ← merges the context fields in afterwards
```

The call's `context` is present from the start, so it is masked. A secret placed in
the context storage is not — it is merged in after masking has already run, and
reaches the log in the clear.

To cover those too, put masking underneath the context processor:

```php
$monolog = $manager->channel('http')->monolog();

$contextProcessor = $monolog->popProcessor();          // ContextInjectingProcessor
$monolog->pushProcessor(new SensitiveMaskingProcessor());
$monolog->pushProcessor($contextProcessor);            // back on top — runs first again
```

The cheaper answer is to keep secrets out of the context storage: it is meant for
request and user identifiers.
