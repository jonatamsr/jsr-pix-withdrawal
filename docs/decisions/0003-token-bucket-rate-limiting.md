# 3. Per-Account Token-Bucket Rate Limiting via Redis


Date: 2026-03-05

## Status

Accepted

## Context

Without rate limiting, a single account (or a misbehaving client) could flood the withdrawal endpoint, saturating the database connection pool and degrading service for all other accounts. A rate-limiting strategy must:

1. Scope limits **per account** — not globally, to avoid penalising legitimate accounts.
2. Allow **short bursts** — a mobile app may retry quickly after a transient failure; a hard limit of 1 req/s with no burst headroom would produce 429s on legitimate retries.
3. Be **stateless at the application layer** — multiple Swoole worker coroutines may handle requests concurrently; state must be shared via an external store.
4. Have **low latency** — the check must not add meaningful overhead to the request path.

Algorithms considered:

| Algorithm | Burst handling | Memory per key | Notes |
|---|---|---|---|
| Fixed window counter | No | Low | Can allow 2× burst at window boundary |
| Sliding window log | No | High (stores timestamps) | Accurate but expensive |
| Sliding window counter | Partial | Medium | Approximation of sliding window |
| Leaky bucket | No | Low | Smooths traffic; no burst |
| **Token bucket** | **Yes** | **Low** | Allows burst up to capacity; regenerates tokens at fixed rate |

## Decision

Implement a **token-bucket algorithm** backed by **Redis**, with the following parameters:

- **Rate:** 1 token per second per account (`account_id` as key namespace).
- **Burst capacity:** 10 tokens.
- **Implementation:** `TokenBucketRateLimiter` wraps Hyperf's `RateLimitHandler` and is injected into `RateLimitMiddleware` via the `RateLimiterInterface` port.
- **Response on limit exceeded:** HTTP 429 Too Many Requests with a JSON error body.

The Redis key pattern is: `rate_limit:{account_id}`. Tokens are consumed atomically using Redis Lua scripts (supplied by `malkusch/lock` via `hyperf/rate-limit`), ensuring correctness under concurrency.

## Consequences

**Positive:**
- Per-account scoping protects the service from a single account monopolising capacity.
- Burst capacity of 10 allows mobile/retry clients to succeed on quick consecutive attempts without false 429s.
- Redis-backed state is shared across all Swoole coroutines and across multiple service instances, making the implementation horizontally scalable.
- `RateLimiterInterface` port means the algorithm can be swapped without touching middleware or controller code.

**Negative / Risks:**
- Requires Redis to be available; a Redis outage will affect rate limiting. The current implementation fails open (request proceeds) if Redis is unreachable, which is acceptable for a development microservice but should be reviewed for production.
- Token-bucket state persists across service restarts (Redis TTL-based), so tokens consumed before a restart are not restored.
- The burst capacity (10) and rate (1/s) parameters are currently hardcoded in configuration; they should be externalised to environment variables for production tuning.
