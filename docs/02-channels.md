# Channels — LoggerManager

A **channel** is a named Monolog instance with its own level, format, and output
target. The framework builds channel configs from env vars; the entry point
activates the right one as default.

---

## Default channels in winter-kernel

| Channel | Activated by | Typical use |
|---------|-------------|-------------|
| `sys`  | Kernel (always default after `init()`) | System-level events, fallback |
| `http` | `public/index.php` → `setDefaultChannel('http')` | HTTP handlers, controllers, services |
| `cli`  | CLI runner → `setDefaultChannel('cli')` | Jobs, queue workers, daemons |

---

## LoggerManager

```php
use Monolog\Level;
use Flytachi\Winter\Logger\LoggerManager;
use Flytachi\Winter\Logger\Context\ProcessContext;

$manager = new LoggerManager(
    contextStorage: new ProcessContext(),
    channels: [
        'sys' => [
            'level'        => Level::Warning,
            'format'       => 'line',
            'output'       => 'stderr',
            'file_path'    => null,
            'file_max'     => 30,
            'syslog_ident' => 'winter',
        ],
        'http' => [
            'level'        => Level::Info,
            'format'       => 'line',
            'output'       => 'stderr',
            'file_path'    => null,
            'file_max'     => 30,
            'syslog_ident' => 'winter',
        ],
        'cli' => [
            'level'        => Level::Debug,
            'format'       => 'line',
            'output'       => 'stdout',
            'file_path'    => null,
            'file_max'     => 30,
            'syslog_ident' => 'winter',
        ],
    ],
);

$logger = $manager->channel('http');
$logger->info('request handled');
```

Channels are built **lazily** and **cached** — the first call to `channel('http')`
creates the Monolog instance; subsequent calls return the same object.

---

## Channel config shape

| Key | Type | Required | Description |
|-----|------|----------|-------------|
| `level` | `Monolog\Level` | yes | Minimum level — records below this are discarded |
| `format` | `'line'` \| `'json'` | yes | Log format |
| `output` | see table below | yes | Where to write |
| `file_path` | `string\|null` | when `output=file` | Absolute path to log file |
| `file_max` | `int` | no (default `30`) | Max rotated files to keep |
| `syslog_ident` | `string` | no (default `'winter'`) | Syslog process identifier |

---

## Output targets

| Value | Handler | Notes |
|-------|---------|-------|
| `stdout` | `SafeStreamHandler('php://stdout')` | Swoole, CLI |
| `stderr` | `SafeStreamHandler('php://stderr')` | FPM — safe from broken pipe |
| `syslog` | `SyslogHandler` | Docker / Kubernetes centralized logging |
| `file` | `RotatingFileHandler` | Requires `file_path`; rotates daily, keeps `file_max` files |
| `null` | `NullHandler` | Discards everything — useful in tests |

### FPM + stdout = broken pipe risk

In PHP-FPM, `php://stdout` is the FastCGI response stream to nginx/Apache.
If the client disconnects, writing to it triggers `SIGPIPE` and can kill the
worker process. **Use `stderr` for FPM** — it goes to FPM's error_log which is
independent of the client connection.

---

## Adding dynamic channels

### `withChannel()` — create a new manager with one extra channel

```php
$manager = $manager->withChannel('job', [
    'level'     => Level::Debug,
    'format'    => 'line',
    'output'    => 'file',
    'file_path' => '/var/log/app/job.log',
    'file_max'  => 7,
    'syslog_ident' => 'winter',
]);
```

Returns a **new** `LoggerManager` instance (immutable). The original is unchanged.

### `withContextStorage()` — swap context storage

```php
// Swoole entry point:
$manager = $manager->withContextStorage(new CoroutineContext());
```

Returns a new instance with the same channels but different context storage.

### Via `LoggerFactory::addChannel()`

```php
// After LoggerFactory::setManager() has been called:
LoggerFactory::addChannel('job', [
    'level'     => Level::Debug,
    'format'    => 'line',
    'output'    => 'file',
    'file_path' => '/var/log/app/job.log',
    'file_max'  => 7,
    'syslog_ident' => 'winter',
]);
```

In winter-kernel this is done via:

```php
// bootstrap.php — reads LOG_JOB_* from .env automatically:
Kernel::channel('job');
```

---

## Unknown channel fallback

If `LoggerFactory::getLogger($class, 'job')` is called and `'job'` is not
registered, the logger silently falls back to the current **default channel**
(`sys` / `http` / `cli`). No exception is thrown.

---

## `flush()` — drop channel cache

```php
$manager->flush();
// All cached Monolog instances are dropped; rebuilt on next channel() call
```

Useful after in-process config reload in long-running Swoole workers.

---

## `hasChannel()` — check registration

```php
$manager->hasChannel('job'); // true | false
```
