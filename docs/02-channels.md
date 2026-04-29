# Channels — LoggerManager

A **channel** is a named Monolog instance with its own level, format, and output
target. The framework defines channels; application code picks the right one.

---

## Default channels in winter-kernel

| Channel | Runtime | Typical use |
|---------|---------|-------------|
| `http` | FPM, Swoole | HTTP request handlers, controllers, services |
| `cli` | CLI, jobs, daemons | Console commands, queue workers, scheduled tasks |

---

## LoggerManager

```php
use Monolog\Level;
use Flytachi\Winter\Logger\LoggerManager;
use Flytachi\Winter\Logger\Context\ProcessContext;

$manager = new LoggerManager(
    contextStorage: new ProcessContext(),
    channels: [
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

// Get a channel logger:
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
| `stdout` | `SafeStreamHandler('php://stdout')` | Safe for Swoole, CLI |
| `stderr` | `SafeStreamHandler('php://stderr')` | Safe for FPM — stderr goes to FPM error_log, not the HTTP response |
| `syslog` | `SyslogHandler` | Docker / Kubernetes centralized logging |
| `file` | `RotatingFileHandler` | Requires `file_path`; rotates daily, keeps `file_max` files |
| `null` | `NullHandler` | Discards everything — useful in tests |

### FPM + stdout = broken pipe risk

In PHP-FPM, `php://stdout` is the FastCGI response stream to nginx/Apache.
If the client disconnects, writing to it triggers `SIGPIPE` and can kill the
worker process. **Use `stderr` for FPM** — it goes to FPM's error_log which is
independent of the client connection.

---

## `flush()` — config reload in daemons

Call `flush()` to drop all cached channel instances. The next `channel()` call
will rebuild them from the original config. Useful after in-process config
reload in long-running Swoole workers:

```php
$manager->flush();
// Channels are now rebuilt on next access
```
