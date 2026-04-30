# LoggerFactory & Log facade

`LoggerFactory` is a static facade that provides Java-style per-class loggers
and controls which channel is active for the current runtime.

---

## Bootstrap — entry point responsibilities

The framework kernel calls `setManager()` once. The **entry point** then sets
the active channel and (for Swoole) the context storage.

```php
// public/index.php  — FPM
require '../bootstrap.php';                          // Kernel::init() → setManager(), setDefaultChannel('sys')
LoggerFactory::setDefaultChannel('http');

// swoole_server.php  — Swoole HTTP server
require '../bootstrap.php';
LoggerFactory::setContextStorage(new CoroutineContext());
LoggerFactory::setDefaultChannel('http');

// cli runner / wKernelExecutor
require 'bootstrap.php';
LoggerFactory::setDefaultChannel('cli');
```

---

## `setManager(LoggerManager $manager)`

Called once by the framework kernel. All subsequent calls use this manager.

```php
LoggerFactory::setManager($manager);
```

---

## `setDefaultChannel(string $channel)`

Sets which channel `getLogger()` and `Log::*()` write to when no explicit
channel is given. Called by the entry point, not the kernel.

```php
LoggerFactory::setDefaultChannel('http');  // FPM / Swoole
LoggerFactory::setDefaultChannel('cli');   // CLI / jobs
```

---

## `setContextStorage(ContextStorage $storage)`

Swaps the context storage on the current manager (channels stay the same).
Called by the Swoole entry point before accepting requests.

```php
use Flytachi\Winter\Logger\Context\CoroutineContext;

LoggerFactory::setContextStorage(new CoroutineContext());
```

---

## `addChannel(string $name, array $config)`

Registers an additional channel on the current manager. In winter-kernel this
is wrapped by `Kernel::channel($name)`.

```php
LoggerFactory::addChannel('job', [
    'level'        => Level::Debug,
    'format'       => 'line',
    'output'       => 'file',
    'file_path'    => '/var/log/app/job.log',
    'file_max'     => 7,
    'syslog_ident' => 'winter',
]);
```

---

## `getLogger(class, ?channel)` — per-class logger

```php
// Uses default channel set by the entry point:
LoggerFactory::getLogger(UserService::class)->info('user created', ['id' => 42]);
LoggerFactory::getLogger($this)->warning('slow query');

// Explicit channel override:
LoggerFactory::getLogger(MyJob::class, 'cli')->debug('step done');
```

**Behaviour:**
- If the requested channel is registered → uses it
- If the channel is unknown → silently falls back to the default channel
- The short class name appears as `(ClassName)` in the log line
- The full FQCN is stored in `context['class']` for log aggregator queries
- Result is **cached** per `channel:FQCN` — no re-allocation on repeated calls

**Output (`SpringLineFormatter`):**
```
[2024-01-01 12:00:00] [INFO ] -http- (UserService): user created {"id":42,"class":"App\\Service\\UserService"}
[2024-01-01 12:00:00] [DEBUG] -cli- (MyJob): step done {"class":"App\\Job\\MyJob"}
```

### Passing `$this`

```php
LoggerFactory::getLogger($this)->info('...');
// equivalent to LoggerFactory::getLogger(static::class)
```

---

## `channel(string $name)` — raw channel logger

No per-class naming. Throws `InvalidArgumentException` if the channel is not registered.

```php
LoggerFactory::channel('http')->warning('rate limit hit');
LoggerFactory::channel('cli')->debug('job started');
```

---

## `logger()` — default channel logger

Returns the raw logger for the current default channel. Used internally by
the `Log` facade.

```php
LoggerFactory::logger()->info('message');
// equivalent to LoggerFactory::channel($defaultChannel)->info('message')
```

---

## `Log` facade — quick static shortcut

`Log` is a one-liner shortcut to the default channel. No class name is attached.

```php
use Flytachi\Winter\Logger\Log;

Log::debug('cache miss');
Log::info('user created', ['id' => $id]);
Log::warning('retrying', ['attempt' => 3]);
Log::error('payment failed', ['order' => $orderId]);
Log::critical('db down');
Log::alert('disk full');
Log::emergency('system crash');
```

Equivalent to `LoggerFactory::logger()->{level}(...)`.

---

## `withContext(array)` — bound context

Returns a new logger instance with additional fields merged into every call.

```php
$log = LoggerFactory::getLogger(self::class)
    ->withContext(['request_id' => $requestId, 'user_id' => $userId]);

$log->info('payment started');  // carries request_id + user_id
$log->info('payment done');     // carries request_id + user_id
```

---

## `reset()` — drop per-class cache

```php
LoggerFactory::reset();
```

Clears the per-class logger cache. Useful in daemons after config reload or in
PHPUnit `setUp()`:

```php
protected function setUp(): void
{
    LoggerFactory::setManager($this->buildManager());
}
```

---

## Summary

| Method | Purpose | Cached |
|--------|---------|--------|
| `setManager($m)` | Initialize factory (kernel) | — |
| `setDefaultChannel($ch)` | Set active channel (entry point) | — |
| `setContextStorage($s)` | Swap context storage (Swoole entry point) | — |
| `addChannel($name, $cfg)` | Register extra channel | — |
| `getLogger($class, ?$ch)` | Per-class logger, fallback to default | Yes, per `channel:FQCN` |
| `channel($name)` | Raw channel logger | Inside `LoggerManager` |
| `logger()` | Default channel logger | Inside `LoggerManager` |
| `reset()` | Clear per-class cache | — |
