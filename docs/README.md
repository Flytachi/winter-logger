# winter-logger — internal reference

Technical documentation for the library itself: exact contracts, what each piece is
responsible for, and the reasoning behind decisions that are not obvious from the code.

This is **not** the getting-started guide — that is the [root README](../README.md) and the
[user documentation](https://winterframe.net/packages/logger). These pages assume logging
already works and you now need to know precisely what happens to a record, or why a piece
behaves the way it does. Written for the people who maintain the library, wire it into a
framework, or debug a log line that came out wrong.

---

## Map

| # | Page | Go here when |
|---|------|--------------|
| 00 | [Overview](00-overview.md) | You want the architecture in one picture — storage, manager, factory, facade |
| 01 | [Installation](01-installation.md) | Requirements, optional Monolog, what happens without it |
| 02 | [Channels](02-channels.md) | Declaring channels, the config shape, outputs, dynamic registration |
| 03 | [LoggerFactory & Log](03-logger-factory.md) | Bootstrap, per-class loggers, the default channel, the facade |
| 04 | [Context](04-context.md) | Fields carried by every record, and how they stay per unit of work |
| 05 | [Handlers & formatters](05-handlers-formatters.md) | Where a record goes and what it looks like on arrival |
| 06 | [Processors](06-processors.md) | Context injection and sensitive-value masking |

---

## Routes through it

- **"Where do I wire this up?"** → [03](03-logger-factory.md): the entry point sets the
  manager, the default channel and the context storage; the kernel only builds the config.
- **"Why is my context empty / leaking between requests?"** → [04](04-context.md). Under
  Swoole the storage must be `CoroutineContext`, and whoever owns the unit of work has to
  call `clear()` when it ends.
- **"Nothing is being logged"** → [01](01-installation.md): without `monolog/monolog`
  every channel silently becomes a `NullLogger`. That is deliberate, and it is quiet.
- **"How do I add a channel at runtime?"** → [02](02-channels.md) — `addChannel()` on the
  factory, `withChannel()` on the manager, and why the cache has to be flushed.
- **"What are these fields in the line?"** → [05](05-handlers-formatters.md) for the line
  format, [06](06-processors.md) for where the values came from.
- **"How do I stop passwords reaching the log?"** → [06](06-processors.md).

---

## Invariants these pages rely on

Everything else is detail; break one of these and the rest stops being true.

1. **The library never reads the environment.** No `getenv`, no SAPI detection, no path
   resolution — the framework builds the channel config and passes it in. This is what
   keeps the package testable and reusable outside Winter.
2. **Monolog is optional.** If it is absent, every channel resolves to `Psr\Log\NullLogger`
   rather than throwing. Logging must never be the reason an application fails to boot.
3. **Context belongs to a unit of work**, not to the process — a coroutine under Swoole, the
   process under FPM and CLI. The storage implementation is what makes the same calling
   code correct in both.
4. **Channel loggers are built once and cached.** `flush()` is the only way to rebuild them,
   which matters in a long-running daemon whose config changed.
5. **The `Log` facade carries seven of the eight PSR-3 levels.** `emergency` is left off
   deliberately; it is reached through `LoggerFactory::logger()` when genuinely warranted.

---

## Keeping it honest

Every code sample here is meant to run as written against the current `src/`. When you
change behaviour, the page that describes it is part of the change — a sample that no longer
executes is worse than no sample, because it is trusted. Claims about what a record looks
like belong in a test, and the page should say which one.
