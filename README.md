# Winter Logger

[![Latest Version on Packagist](https://img.shields.io/packagist/v/flytachi/winter-logger.svg)](https://packagist.org/packages/flytachi/winter-logger)
[![PHP Version Require](https://img.shields.io/packagist/php-v/flytachi/winter-logger.svg?style=flat-square)](https://packagist.org/packages/flytachi/winter-logger)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg)](LICENSE)

A PSR-3 logger that stays correct when one process serves many requests at once. It wraps
Monolog with per-unit-of-work context isolation, Spring Boot-style output and a Java-style
static factory, so the same logging code behaves the same under FPM, CLI and Swoole.

The library is **infrastructure-agnostic**: it never reads env vars and never detects
Docker, the SAPI or Swoole. The framework that boots it builds the channel config and
passes it in — which is what keeps the package testable and usable outside Winter.

📖 **[Documentation](https://winterframe.net/packages/logger)** · [Quick start](https://winterframe.net/packages/logger/quickstart) · [API reference](https://winterframe.net/packages/logger/api-reference) · [Channel config](https://winterframe.net/packages/logger/channel-config)

---

## Installation

```bash
composer require flytachi/winter-logger monolog/monolog
```

Requires PHP **8.3+** and `psr/log ^3.0`. `monolog/monolog ^3.5` is a suggestion rather
than a dependency — install without it and every channel resolves to a `NullLogger` instead
of failing. `ext-swoole` is needed only for `CoroutineContext`.

---

## Quick start

Build a manager with your channels and hand it to the factory once at bootstrap:

```php
use Flytachi\Winter\Logger\{LoggerFactory, LoggerManager};
use Flytachi\Winter\Logger\Context\ProcessContext;
use Monolog\Level;

LoggerFactory::setManager(new LoggerManager(
    contextStorage: new ProcessContext(),      // CoroutineContext under Swoole
    channels: [
        'http' => ['level' => Level::Info,  'format' => 'line', 'output' => 'stderr'],
        'cli'  => ['level' => Level::Debug, 'format' => 'line', 'output' => 'stdout', 'color' => true],
    ],
));

LoggerFactory::setDefaultChannel('http');
```

Then log from anywhere:

```php
use Flytachi\Winter\Logger\{Log, LoggerFactory};

Log::info('user created', ['id' => 42]);                             // default channel
LoggerFactory::getLogger(UserService::class)->info('cache warmed');  // named after the class
LoggerFactory::channel('cli')->warning('rate limit hit');            // a specific channel
```

```
[2026-01-01 12:00:00] [INFO ] -http- [4821]: user created {"id":42}
[2026-01-01 12:00:00] [INFO ] -http- [4821] (UserService): cache warmed {"class":"UserService"}
[2026-01-01 12:00:00] [WARN ] -cli-  [4821]: rate limit hit
```

The last line goes to `cli`, which sits at `Debug` — a `debug()` call on the default `http`
channel above would have been filtered out, since that one is configured at `Info`.

---

## What you get

- **Context that cannot leak** — fields set once per request appear in every record, and
  live in the coroutine under Swoole, in the process under FPM and CLI.
- **Per-class loggers** — `LoggerFactory::getLogger(self::class)` names the record after the
  class that wrote it, cached per class.
- **Bound context** — `withContext([...])` returns a logger carrying those fields into every
  later call, without mutating the original.
- **Five outputs** — `stdout`, `stderr`, `syslog`, rotating `file`, and `null` for tests.
- **Two formats** — a readable line for humans, JSON for collectors, with optional ANSI
  colour on the line.
- **Sensitive-value masking** — passwords and tokens are replaced before a record is
  written, not after it is read.
- **Survives a closed pipe** — a `SIGPIPE`-safe stream handler, so a reader that went away
  cannot take the process down.
- **Monolog optional** — absent, everything degrades to `NullLogger`; logging never prevents
  a boot.

---

## Request-scoped context

Set the fields once at the start of the unit of work and every record picks them up:

```php
$storage = LoggerFactory::contextStorage();

$storage->set('request_id', $requestId);
$storage->set('user_id', $userId);

// ... anywhere downstream
Log::info('processing');   // carries request_id and user_id

$storage->clear();         // at the end — mandatory in a long-running process
```

Under Swoole, pass `CoroutineContext` instead of `ProcessContext` and each concurrent
request gets its own bag — see
[Context isolation](https://winterframe.net/packages/logger/context-isolation).

---

## Documentation

The user-facing documentation lives at **[winterframe.net/packages/logger](https://winterframe.net/packages/logger)**
(the link picks your language; RU and EN are both complete).

**Start here**

| Page | What it answers |
|------|-----------------|
| [Introduction](https://winterframe.net/packages/logger/intro) | What the package is, and what it adds to Monolog |
| [Installation](https://winterframe.net/packages/logger/installation) | Requirements, optional Monolog, Swoole |
| [Quick start](https://winterframe.net/packages/logger/quickstart) | Bootstrap, first channel, first record |
| [Mental model](https://winterframe.net/packages/logger/mental-model) | Storage, manager, factory, facade — who does what |

**Guides**

| Page | What it answers |
|------|-----------------|
| [Framework integration](https://winterframe.net/packages/logger/framework-integration) | Wiring it into an application's entry point |
| [Request context](https://winterframe.net/packages/logger/request-context) | Attaching fields to every record of a request |
| [Swoole coroutines](https://winterframe.net/packages/logger/swoole-coroutines) | Keeping context isolated under concurrency |
| [Dynamic channels](https://winterframe.net/packages/logger/dynamic-channels) | Adding a channel after bootstrap |
| [Masking sensitive data](https://winterframe.net/packages/logger/mask-sensitive-data) | Keeping secrets out of the log |

**Reference**

| Page | What it answers |
|------|-----------------|
| [API reference](https://winterframe.net/packages/logger/api-reference) | Every class and method |
| [Channel config](https://winterframe.net/packages/logger/channel-config) | Each config key, output target and format |
| [Log format](https://winterframe.net/packages/logger/log-format) | What each segment of a line means |

**Deep dive**

| Page | What it answers |
|------|-----------------|
| [Log record lifecycle](https://winterframe.net/packages/logger/log-record-lifecycle) | What happens between the call and the write |
| [Context isolation](https://winterframe.net/packages/logger/context-isolation) | Why storage belongs to a unit of work |
| [Output and broken pipe](https://winterframe.net/packages/logger/output-and-broken-pipe) | Surviving a reader that went away |
| [Monolog optional](https://winterframe.net/packages/logger/monolog-optional) | What degrades without it, and how quietly |

Classes in this package carry an `@link` to their page, so the same documentation is one
click away from your IDE.

---

## Contributing

Internal technical notes — exact contracts, invariants, and the reasoning behind decisions
that are not obvious from the code — live in [`docs/`](docs/README.md). Read that before
changing how a record is built.

```bash
composer test        # phpunit
composer test-detail # phpunit --testdox
composer cs-check    # phpcs
composer cs-fix      # phpcbf
```

---

## License

MIT License. See [LICENSE](LICENSE).
