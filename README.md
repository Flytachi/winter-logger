# Winter Logger

[![Latest Version on Packagist](https://img.shields.io/packagist/v/winter/logger.svg)](https://packagist.org/packages/winter/logger)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg)](LICENSE)

**winter/logger** — multi-runtime PSR-3 logger for the Winter framework.
Wraps Monolog with coroutine-safe context isolation, Spring Boot-style output,
and a Java-style static factory.

The library is **infrastructure-agnostic**: it knows nothing about env vars,
Docker, SAPI, or Swoole detection — that responsibility belongs to the framework
that boots it.

---

## Requirements

- PHP >= 8.3
- `psr/log` ^3.0
- `monolog/monolog` ^3.5 *(suggested — without it every logger silently becomes `NullLogger`)*
- `ext-swoole` *(optional — required only for `CoroutineContext`)*

## Installation

```bash
composer require winter/logger monolog/monolog
```

Monolog-free (every logger will be a no-op `NullLogger`):

```bash
composer require winter/logger
```

---

## Quick Start

### 1. Build a `LoggerManager`

The manager accepts a ready-made config — the **framework** resolves env vars
and infrastructure details before calling this:

```php
use Monolog\Level;
use Flytachi\Winter\Logger\LoggerManager;
use Flytachi\Winter\Logger\Context\ProcessContext;   // FPM / CLI
// use Flytachi\Winter\Logger\Context\CoroutineContext; // Swoole

$manager = new LoggerManager(
    contextStorage: new ProcessContext(),
    channels: [
        'http' => [
            'level'        => Level::Info,
            'format'       => 'line',       // 'line' | 'json'
            'output'       => 'stderr',     // 'stdout' | 'stderr' | 'syslog' | 'file' | 'null'
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
```

### 2. Register with `LoggerFactory`

```php
use Flytachi\Winter\Logger\LoggerFactory;

LoggerFactory::setManager($manager);
```

### 3. Log from anywhere

```php
use Flytachi\Winter\Logger\LoggerFactory;

// Per-class logger — Java-style:
LoggerFactory::getLogger(UserService::class, 'http')->info('user created', ['id' => 42]);
LoggerFactory::getLogger($this, 'cli')->debug('job started');

// Raw channel:
LoggerFactory::channel('http')->warning('rate limit hit');

// Bound context — fields appear in every subsequent call:
$log = LoggerFactory::getLogger(self::class, 'http')->withContext(['request_id' => $id]);
$log->info('processing');
$log->info('done');
```

**Output (line format)**

```
[2024-01-01 12:00:00] [INFO ] [UserService]: user created {"id":42,"class":"App\\Service\\UserService"}
[2024-01-01 12:00:00] [WARN ] [http]: rate limit hit
```

---

## Request-scoped context

Set fields once at the start of a request/job and they appear in every log line
automatically via `ContextInjectingProcessor`:

```php
// At request start (FPM middleware / Swoole onRequest):
$manager->contextStorage()->set('request_id', $requestId);
$manager->contextStorage()->set('user_id',    $userId);

// At request end — must clear in long-running processes to prevent leaks:
$manager->contextStorage()->clear();
```

For **Swoole** use `CoroutineContext` — it isolates context per coroutine so
concurrent requests never bleed into each other.

---

## Channel config reference

| Key | Type | Description |
|-----|------|-------------|
| `level` | `Monolog\Level` | Minimum level to handle |
| `format` | `'line'` \| `'json'` | Log format |
| `output` | `'stdout'` \| `'stderr'` \| `'syslog'` \| `'file'` \| `'null'` | Where to write |
| `file_path` | `string\|null` | Required when `output=file` |
| `file_max` | `int` | Max rotated files to keep (default `30`) |
| `syslog_ident` | `string` | Syslog process identifier (default `'winter'`) |

---

## Processors

**`ContextInjectingProcessor`** is added to every channel automatically — no config needed.

**`SensitiveMaskingProcessor`** is optional. Add it after building the manager:

```php
use Flytachi\Winter\Logger\Processor\SensitiveMaskingProcessor;

$monolog = $manager->channel('http')->monolog();
$monolog->pushProcessor(new SensitiveMaskingProcessor(['my_secret']));
```

Default masked keys: `password`, `token`, `secret`, `api_key`, `authorization`,
`cookie`, `credit_card`, `cvv`, `ssn`, `pin`, and more.

---

## Documentation

| File | Topic |
|------|-------|
| [00-overview.md](docs/00-overview.md) | Architecture — how the pieces fit together |
| [01-installation.md](docs/01-installation.md) | Installation, optional Monolog |
| [02-channels.md](docs/02-channels.md) | `LoggerManager`, channel config, output types |
| [03-logger-factory.md](docs/03-logger-factory.md) | `LoggerFactory`, `getLogger`, `withContext` |
| [04-context.md](docs/04-context.md) | `ProcessContext`, `CoroutineContext`, lifecycle |
| [05-handlers-formatters.md](docs/05-handlers-formatters.md) | `SafeStreamHandler`, formatters, broken pipe |
| [06-processors.md](docs/06-processors.md) | `ContextInjectingProcessor`, `SensitiveMaskingProcessor` |

---

## License

MIT License. See [LICENSE](LICENSE).
