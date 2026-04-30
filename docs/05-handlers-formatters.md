# Handlers and Formatters

---

## Handlers

### `SafeStreamHandler`

Extends Monolog's `StreamHandler` with one override: `streamWrite()` uses
`@fwrite()` and never throws on write failure.

```php
use Flytachi\Winter\Logger\Handler\SafeStreamHandler;
use Monolog\Level;

$handler = new SafeStreamHandler('php://stderr', Level::Debug);
```

**Why it exists — the broken pipe problem:**

In PHP-FPM, `php://stdout` is the FastCGI response stream. If the HTTP client
disconnects before the response is complete, the next write to stdout triggers
`SIGPIPE`, which kills the FPM worker by default.

`SafeStreamHandler` suppresses the `E_WARNING` from `fwrite()` and swallows
the failure silently — the worker survives, the log line is simply lost.

> **For FPM use `php://stderr`** rather than `php://stdout` — FPM's stderr goes
> to the error_log file independently of the client connection, so broken pipe
> never occurs. Swoole workers can safely use `php://stdout` because Swoole
> manages HTTP responses through its own async I/O, not through PHP's stdout.

---

### Other handlers (via `HandlerFactory`)

| `output` value | Monolog handler | Notes |
|---------------|-----------------|-------|
| `stdout` | `SafeStreamHandler('php://stdout')` | Swoole, CLI |
| `stderr` | `SafeStreamHandler('php://stderr')` | FPM — safe from broken pipe |
| `syslog` | `SyslogHandler` | Docker / Kubernetes |
| `file` | `RotatingFileHandler` | Daily rotation, `file_max` files kept |
| `null` | `NullHandler` | Discards everything — tests |

---

## Formatters

### `SpringLineFormatter` (default for `format=line`)

Produces a human-readable single-line format:

```
[datetime] [LEVEL] -channel- (ClassName): message {json}
[datetime] [LEVEL] -channel-: message {json}
```

- `-channel-` — always the registered channel name (`http`, `cli`, `sys`, …)
- `(ClassName)` — short class name, only present when logger was created via
  `LoggerFactory::getLogger(MyClass::class)`. Absent for raw `channel()` calls.
- `{json}` — merged `context` + `extra` (request_id, class FQCN, etc.)

**Level labels (fixed 5 chars):**

| Level | Label |
|-------|-------|
| Debug | `DEBUG` |
| Info | `INFO ` |
| Notice | `NOTIC` |
| Warning | `WARN ` |
| Error | `ERROR` |
| Critical | `CRIT ` |
| Alert | `ALERT` |
| Emergency | `EMERG` |

**Examples:**

```
[2024-01-01 12:00:00] [INFO ] -http-: request handled {"request_id":"abc-123"}
[2024-01-01 12:00:00] [DEBUG] -http- (PpaConnectionPool): FPM connection opened: TestDbConfig {"class":"App\\Pool\\PpaConnectionPool"}
[2024-01-01 12:00:00] [ERROR] -http- (UserService): db timeout {"class":"App\\UserService","attempt":3}
[2024-01-01 12:00:00] [DEBUG] -cli- (MyJob): step done {"class":"App\\Job\\MyJob"}
[2024-01-01 12:00:00] [WARN ] -sys-: disk usage high
```

---

### `JsonFormatter` (for `format=json`)

Standard Monolog `JsonFormatter` — one JSON object per line, newline-delimited.
Suitable for log aggregators (Loki, Elasticsearch, Datadog).

```json
{"message":"user created","context":{"id":42,"class":"App\\Service\\UserService"},"level":200,"level_name":"INFO","channel":"http","datetime":"2024-01-01T12:00:00+00:00","extra":{"request_id":"abc-123"}}
```

---

## `FormatterFactory`

```php
use Flytachi\Winter\Logger\Factory\FormatterFactory;

$formatter = FormatterFactory::make('line');  // SpringLineFormatter
$formatter = FormatterFactory::make('json');  // JsonFormatter
```

Throws `InvalidArgumentException` for unknown format strings.

---

## `HandlerFactory`

```php
use Flytachi\Winter\Logger\Factory\HandlerFactory;
use Monolog\Level;

$handler = HandlerFactory::make([
    'output'       => 'stderr',
    'level'        => Level::Info,
    'file_path'    => null,
    'file_max'     => 30,
    'syslog_ident' => 'winter',
]);
```

Throws `InvalidArgumentException` for unknown `output` values or when
`output=file` is used without `file_path`.
