# LoggerFactory

`LoggerFactory` is a static facade that provides Java-style per-class loggers.
It is the primary way application code interacts with the logger.

---

## Bootstrap (once at application start)

```php
use Flytachi\Winter\Logger\LoggerFactory;

LoggerFactory::setManager($manager);
```

Call this once when the framework boots. All subsequent `getLogger()` calls
across the entire codebase will use this manager.

---

## `getLogger(class, channel)` — per-class logger

```php
use Flytachi\Winter\Logger\LoggerFactory;

class UserService
{
    public function create(array $data): void
    {
        LoggerFactory::getLogger(self::class, 'http')
            ->info('user created', ['id' => $data['id']]);
    }
}
```

**What it does:**
- Takes the short class name (e.g. `UserService`) as the Monolog channel name —
  this appears in every log line for easy filtering
- Stores the full FQCN in `context['class']` for exact lookup in Kibana / Loki
- The result is **cached per `channel:FQCN`** — no re-allocation on every call

**Output:**
```
[2024-01-01 12:00:00] [INFO ] [UserService]: user created {"id":42,"class":"App\\Service\\UserService"}
```

### Passing `$this`

```php
LoggerFactory::getLogger($this, 'http')->info('...');
// equivalent to LoggerFactory::getLogger(static::class, 'http')
```

---

## `channel(name)` — raw channel logger

```php
// No per-class naming, just the raw channel:
LoggerFactory::channel('http')->warning('rate limit hit');
LoggerFactory::channel('cli')->debug('job started');
```

---

## `withContext(array)` — bound context

Returns a new logger instance with additional fields merged into every call.
The original instance is not mutated.

```php
$log = LoggerFactory::getLogger(self::class, 'http')
    ->withContext(['request_id' => $requestId, 'user_id' => $userId]);

$log->info('payment started');   // carries request_id + user_id
$log->info('payment done');      // carries request_id + user_id
```

Context merge precedence (lowest → highest):

```
boundContext (withContext)  →  per-call context
```

Global request context (e.g. `request_id` set via `contextStorage->set()`) is
injected separately into `extra` by `ContextInjectingProcessor` and does not
conflict with `context`.

---

## `reset()` — drop per-class cache

```php
LoggerFactory::reset();
```

Clears the per-class logger cache. Useful in long-running daemons after config
reload, or in PHPUnit `setUp()` between tests:

```php
protected function setUp(): void
{
    LoggerFactory::setManager($this->buildManager());
    LoggerFactory::reset();
}
```

---

## Exception when not initialized

If `getLogger()` or `channel()` is called before `setManager()`:

```
RuntimeException: Flytachi\Winter\Logger\LoggerFactory is not initialized.
Call LoggerFactory::setManager($manager) at application bootstrap.
```

---

## Summary

| Method | Returns | Cached |
|--------|---------|--------|
| `getLogger(class, channel)` | Per-class `Logger` with short name as Monolog channel | Yes, per `channel:FQCN` |
| `channel(name)` | Raw channel `Logger` from `LoggerManager` | Yes, inside `LoggerManager` |
| `withContext(array)` | New `Logger` instance with merged bound context | No (new object) |
| `setManager($m)` | void | — |
| `reset()` | void | Clears per-class cache |
