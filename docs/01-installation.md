# Installation

---

## Composer

```bash
# With Monolog (actual logging):
composer require winter/logger monolog/monolog

# Without Monolog (every logger will be a silent NullLogger):
composer require winter/logger
```

Monolog is listed as `suggest` in `composer.json` so it is never installed
automatically. This lets library consumers opt-out of logging entirely without
any code changes.

---

## Optional dependencies

| Package | Purpose |
|---------|---------|
| `monolog/monolog ^3.5` | Required for actual log output |
| `ext-swoole` | Required for `CoroutineContext` (coroutine-local context in Swoole) |
| `ext-openswoole` | Alternative to `ext-swoole` |

---

## Monolog-free mode

When `monolog/monolog` is not installed, `LoggerManager::channel()` returns
`Psr\Log\NullLogger` for every channel. No exceptions, no warnings — the code
just runs silently.

This is useful for:
- Libraries that optionally support logging
- Microservices where logging is toggled per environment
- Testing scenarios where log output is irrelevant

---

## Framework integration

In **winter-kernel**, the framework reads env vars, resolves the correct output
target (stdout / stderr / syslog / file), picks the right `ContextStorage`
implementation based on runtime (FPM / Swoole / CLI), and passes the finished
config to `LoggerManager`.

The library itself never touches `getenv()`, `$_ENV`, or any infrastructure
detection — that is intentionally the framework's responsibility.
