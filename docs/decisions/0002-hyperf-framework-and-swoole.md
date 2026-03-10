# 2. Hyperf Framework + PHP 8.4 + Swoole


Date: 2026-03-05

## Status

Accepted

## Context

A PIX withdrawal service must handle high concurrency: multiple accounts can submit withdrawals simultaneously, and each request involves I/O-bound operations (MySQL, Redis, SMTP, OpenTelemetry export). Traditional PHP runtimes (PHP-FPM, Apache mod_php) follow a shared-nothing, request-per-process model where the application boots from scratch on every request and blocks the process during I/O waits. This model incurs significant overhead per request and scales poorly under load without a large number of worker processes.

## Decision

Use **Hyperf** as the application framework running on **Swoole** (PHP extension) with **PHP 8.4**.

- **Swoole** replaces PHP-FPM with a persistent, event-driven server process. The server boots once and handles all requests in coroutines — I/O operations (MySQL queries, Redis calls, HTTP outbound requests) yield the coroutine and let other coroutines run, without blocking an OS thread.
- **Hyperf** provides coroutine-aware implementations of all common framework components: DI container (PHP-DI style with annotations), Eloquent ORM via `hyperf/database`, Redis client, Crontab scheduler, Event Dispatcher, and HTTP routing.
- **PHP 8.4** is chosen for the latest performance improvements (JIT, typed class properties, match expressions, fibers) and long-term support.

## Consequences

**Positive:**
- Eliminates per-request bootstrap overhead; the DI container, database connection pool and route tree are initialized once at startup.
- Non-blocking I/O via coroutines allows a single process to handle hundreds of concurrent requests with a small number of OS threads.
- Coroutine-safe connection pooling for MySQL and Redis reduces connection overhead.
- Hyperf's component ecosystem maps 1:1 to familiar Laravel/Symfony concepts, reducing the learning curve.

**Negative / Risks:**
- Swoole's persistent process model means state leaks between requests if not handled carefully (e.g., global variables, static properties). Hyperf mitigates this with coroutine-scoped context, but developers must be aware.
- Not all third-party PHP libraries are coroutine-safe; blocking calls inside a coroutine block the entire event loop. Third-party integrations must be reviewed.
- Debugging and profiling tools (Xdebug, Blackfire) have limited or no coroutine support; PCOV / xdebug coverage collection requires care.
- Deployment is more complex than PHP-FPM: requires a running daemon process, graceful reload strategy, and health checks.
