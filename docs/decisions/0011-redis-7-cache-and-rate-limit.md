# 11. Redis 7 for Idempotency Cache and Rate-Limit State


Date: 2026-03-05

## Status

Accepted

## Context

Two service features require fast, shared, TTL-managed key-value storage:

1. **Idempotency cache** (ADR-0004): stores serialised HTTP responses keyed by `X-Idempotency-Key` with a 24-hour TTL.
2. **Rate-limit token buckets** (ADR-0003): stores per-account token counts with sub-second precision updates.

Both features require:
- **Sub-millisecond read/write latency** — both are on the hot request path.
- **Atomic operations** — token-bucket updates use Lua scripts; idempotency set-if-not-exists semantics.
- **TTL-based expiry** — keys expire automatically without a background cleanup job.
- **Shared state** across all Swoole coroutines (and potentially multiple service replicas).

Options considered:

| Store | Latency | Atomic operations | TTL | Notes |
|---|---|---|---|---|
| **Redis 7** | Sub-ms | Lua, MULTI/EXEC, SET NX | Native | Industry standard for shared ephemeral state |
| Redis 6 | Sub-ms | Lua, MULTI/EXEC | Native | Older; misses Redis 7 function improvements |
| Memcached | Sub-ms | CAS only | Native | No Lua scripts; no pub/sub; less flexible |
| MySQL (separate table) | ~1–5 ms | Transactions | Requires cleanup job | Adds contention to the primary DB; slower |
| In-process (PHP array) | ~0 ms | Coroutine mutex | Manual | Not shared across process restarts or replicas |

## Decision

Use **Redis 7** for both idempotency caching and rate-limit state storage.

- Accessed via `hyperf/redis` with coroutine-aware connection pooling.
- Two logical key namespaces (using key prefix conventions):
  - `idempotency:{X-Idempotency-Key}` — serialised HTTP response; TTL 86400 s (24 h).
  - `rate_limit:{account_id}` — token-bucket state managed by `hyperf/rate-limit` Lua scripts; TTL driven by rate configuration.
- Connection pool size configurable via `REDIS_POOL_MAX`.
- Redis is run as a standalone container (`redis:7-alpine`) with no persistence (AOF/RDB disabled) — both datasets are ephemeral by design; losing them on Redis restart is acceptable (idempotency window resets, rate-limit buckets refill).

## Consequences

**Positive:**
- Redis satisfies both use cases with a single, well-understood infrastructure component.
- Lua-script-based atomic token-bucket updates are natively supported; no emulation needed.
- `SET NX EX` (set-if-not-exists with expiry) provides safe idempotency key reservation in a single round-trip.
- Hyperf's connection pool avoids per-request TCP handshakes; connections are reused across coroutines.
- Redis 7 `LMPOP` / function improvements are available for future use (e.g., server-side scripting for complex rate-limit algorithms).

**Negative / Risks:**
- Redis is an in-memory store (without persistence configured): a Redis restart loses all idempotency keys and rate-limit state. During the restart window, previously idempotent requests may be re-processed. For production, consider enabling AOF persistence for the idempotency namespace.
- A Redis outage causes both idempotency and rate-limit middleware to fail. The current implementation fails open (requests proceed); see ADR-0004 and ADR-0003 for details.
- Memory footprint grows with active idempotency keys; 24-hour TTL caps the growth, but high request volume may require `maxmemory` + `allkeys-lru` eviction policy in production.
