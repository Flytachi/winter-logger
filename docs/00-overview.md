# Winter Logger — Overview

**winter/logger** is a multi-runtime PSR-3 logger for the Winter framework.

---

## Design principles

| Principle | How it's applied |
|-----------|-----------------|
| Infrastructure-agnostic | The library never reads env vars or detects Docker/SAPI — the framework does that before passing config |
| Monolog optional | If `monolog/monolog` is not installed every logger silently becomes `Psr\Log\NullLogger` |
| Runtime-safe context | `ContextStorage` implementations prevent context leaking between concurrent requests/coroutines |
| Java-style factory | `LoggerFactory::getLogger(MyClass::class, 'http')` gives per-class named loggers with caching |

---

## How the pieces fit together

```
ContextStorage (interface)
    ├── ProcessContext     — one array per process (FPM / CLI)
    └── CoroutineContext   — Swoole\Coroutine::getContext(), one bag per coroutine

LoggerManager
    ├── accepts channelsConfig (built by the framework from env vars)
    ├── lazily builds and caches Monolog instances per channel
    └── each channel gets ContextInjectingProcessor automatically

Logger (implements LoggerInterface)
    ├── wraps MonologLogger
    ├── merges boundContext (from withContext()) into every call
    └── contextStorage injected via ContextInjectingProcessor on the Monolog side

LoggerFactory (static)
    ├── setManager($manager)  — called once at bootstrap
    ├── getLogger(class, channel)  — per-class cached Logger (Monolog channel = short class name)
    └── channel(name)  — raw channel logger

HandlerFactory (static)
    └── makes: SafeStreamHandler | SyslogHandler | RotatingFileHandler | NullHandler

FormatterFactory (static)
    └── makes: SpringLineFormatter | JsonFormatter

SafeStreamHandler (extends StreamHandler)
    └── overrides streamWrite() — uses @fwrite, never throws on broken stream/pipe
```

---

## Documentation index

| # | File | Contents |
|---|------|----------|
| 01 | [01-installation.md](01-installation.md) | Installation, Monolog as optional dependency |
| 02 | [02-channels.md](02-channels.md) | `LoggerManager`, channel config shape, output types |
| 03 | [03-logger-factory.md](03-logger-factory.md) | `LoggerFactory`, `getLogger`, `withContext`, caching |
| 04 | [04-context.md](04-context.md) | `ProcessContext`, `CoroutineContext`, request lifecycle |
| 05 | [05-handlers-formatters.md](05-handlers-formatters.md) | Handlers, formatters, broken pipe protection |
| 06 | [06-processors.md](06-processors.md) | `ContextInjectingProcessor`, `SensitiveMaskingProcessor` |
