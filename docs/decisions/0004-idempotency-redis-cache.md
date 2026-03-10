# 4. Idempotency via Redis Response Cache


Date: 2026-03-05

## Status

Accepted

## Context

PIX withdrawal is a financial operation with real-world side effects (balance deduction, notification email). Network failures, client-side retries, and load-balancer timeouts can cause the same request to arrive at the server more than once. Without idempotency controls, a retry of a successful request would produce a second, unintended withdrawal from the same account.

Requirements for the idempotency mechanism:
1. Must be **transparent to use cases** — the deduplication must happen before the use case executes, not inside it.
2. Must guarantee that a replayed request returns **exactly the same HTTP response** (status code, headers, body) as the original.
3. Must be **time-bounded** — idempotency keys should not be stored forever; 24 hours covers all realistic retry windows.
4. Must support the standard `X-Idempotency-Key` header convention used by Stripe, Adyen, and other payment APIs.

## Decision

Implement `IdempotencyMiddleware` as a Hyperf HTTP middleware that:

1. Reads the `X-Idempotency-Key` header on every incoming POST request.
2. **Before forwarding** the request down the middleware pipeline: checks Redis for a cached response under the key `idempotency:{idempotency_key}`.
   - **Cache hit:** returns the cached response immediately, without executing any downstream middleware or controller logic.
   - **Cache miss:** forwards the request to the next middleware.
3. **After the downstream handler returns:** serialises the full HTTP response (status code + headers + body) and stores it in Redis with a 24-hour TTL.

The cache key is the raw value of `X-Idempotency-Key` as provided by the client. No request-body hashing is performed (the client is responsible for using a unique key per logical operation).

Requests without `X-Idempotency-Key` bypass idempotency caching entirely and are processed normally.

## Consequences

**Positive:**
- Use cases and domain logic remain unaware of idempotency; they are always invoked at most once per unique key within the TTL window.
- All replays return an identical HTTP response, satisfying client expectations.
- Storing the serialised response (rather than a boolean flag) avoids reconstructing the response from a DB lookup on cache hits.
- Middleware placement (before `RateLimitMiddleware` and controllers) means replays do not consume rate-limit tokens.

**Negative / Risks:**
- If the upstream client uses the **same key for different requests** (a client bug), the second request will silently receive the first request's response. Documentation and API contracts must clearly state that idempotency keys must be unique per logical operation.
- Redis must remain available; a Redis outage causes `IdempotencyMiddleware` to fall through without caching (fail-open). This means a retry during a Redis outage could result in a duplicate withdrawal — acceptable for a development service, but a production deployment should consider circuit-breaker behaviour.
- The 24-hour TTL is a fixed constant. If a client retries after 24 hours, the key is expired and the operation will re-execute. This is intentional and documented.
- Response serialisation size could be significant for large response bodies; withdrawals return small JSON objects so this is not a concern in practice.
