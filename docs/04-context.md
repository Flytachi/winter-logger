# Context Storage

`ContextStorage` is an abstraction over per-execution-unit storage.
It lets you set fields once (e.g. `request_id`) and have them appear
automatically in every log record for the duration of a request or job.

---

## Interface

```php
interface ContextStorage
{
    public function set(string $key, mixed $value): void;
    public function get(string $key, mixed $default = null): mixed;
    public function all(): array;
    public function forget(string $key): void;
    public function clear(): void;  // call at end of request/job
}
```

Access the storage through the manager:

```php
$storage = $manager->contextStorage();
```

---

## `ProcessContext` — FPM / CLI

One flat array per PHP process. Safe for:
- **FPM** — each request spawns a new process, so the array is fresh every time
- **CLI jobs** — one invocation, one process
- **Daemons** — one process, but call `clear()` between iterations to avoid leaks

```php
use Flytachi\Winter\Logger\Context\ProcessContext;

$manager = new LoggerManager(
    contextStorage: new ProcessContext(),
    channels: [...],
);

// At request/job start:
$manager->contextStorage()->set('request_id', $requestId);
$manager->contextStorage()->set('user_id',    $userId);

// At request/job end:
$manager->contextStorage()->clear();
```

**Not safe for Swoole** — coroutines share the same process, so context
would bleed between concurrent requests.

---

## `CoroutineContext` — Swoole

Uses `Swoole\Coroutine::getContext()`, which returns an `ArrayObject` bound
to the current coroutine. When the coroutine ends Swoole destroys its context
automatically.

```php
use Flytachi\Winter\Logger\Context\CoroutineContext;

$manager = new LoggerManager(
    contextStorage: new CoroutineContext(),
    channels: [...],
);
```

### Lifecycle in a Swoole HTTP server

```php
$server->on('request', function ($request, $response) use ($manager) {
    // Set at coroutine start — visible only in this coroutine:
    $manager->contextStorage()->set('request_id', uniqid('req_'));
    $manager->contextStorage()->set('method',     $request->server['request_method']);
    $manager->contextStorage()->set('path',       $request->server['request_uri']);

    // Handle request...
    $response->end('ok');

    // Optional — Swoole clears the context when the coroutine ends anyway,
    // but explicit clear makes the intent obvious:
    $manager->contextStorage()->clear();
});
```

### Outside a coroutine (e.g. `onWorkerStart`)

`CoroutineContext` falls back to a static array when called outside a coroutine
so the logger keeps working during server bootstrap without throwing.

---

## FPM request lifecycle (manual integration)

```php
// front controller / middleware:
$manager->contextStorage()->set('request_id', $_SERVER['HTTP_X_REQUEST_ID'] ?? uniqid());
$manager->contextStorage()->set('method',     $_SERVER['REQUEST_METHOD']);
$manager->contextStorage()->set('path',       $_SERVER['REQUEST_URI']);
$manager->contextStorage()->set('ip',         $_SERVER['REMOTE_ADDR'] ?? '-');

// register cleanup:
register_shutdown_function(fn () => $manager->contextStorage()->clear());

// handle request...
```

---

## Daemon loop pattern (`ProcessContext`)

```php
while (true) {
    $manager->contextStorage()->set('iteration_id', uniqid('iter_'));

    processNextJob();

    // Clear per-iteration fields; the process continues:
    $manager->contextStorage()->clear();
}
```

---

## Choosing the right implementation

| Runtime | Implementation |
|---------|---------------|
| PHP-FPM | `ProcessContext` — each request = fresh process |
| CLI one-shot | `ProcessContext` — process dies after the job |
| CLI daemon loop | `ProcessContext` + `clear()` between iterations |
| Swoole HTTP | `CoroutineContext` — context isolated per coroutine |
| Swoole WebSocket | `CoroutineContext` — each `onMessage` fires in its own coroutine |
