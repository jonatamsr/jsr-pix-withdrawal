# 5. Pessimistic Locking (SELECT FOR UPDATE) for Balance Deduction


Date: 2026-03-05

## Status

Accepted

## Context

Immediate withdrawals must deduct the account balance atomically. Two or more concurrent withdrawal requests for the same account, processed in overlapping transactions, could each read the same balance, decide it is sufficient, and both proceed — resulting in the balance going negative (a double-spend / overdraft race condition).

Approaches for enforcing serialised balance deductions:

| Approach | Description | Tradeoffs |
|---|---|---|
| Application-level mutex (Redis lock) | Acquire a distributed lock before reading the balance | Extra round-trip to Redis; lock TTL must be tuned; not composable with DB transaction |
| Optimistic locking (version column) | Read balance + version, update only if version unchanged, retry on conflict | No blocking; high-conflict workloads cause excessive retries |
| **Pessimistic locking (`SELECT … FOR UPDATE`)** | Lock the account row at read time within the transaction | Serialises concurrent access at DB level; no retries needed; composable |
| Serialisable transaction isolation | Entire transaction runs at SERIALISABLE level | Heavier lock overhead; affects all reads in transaction, not just the balance check |

## Decision

Use **pessimistic row-level locking** via `lockForUpdate()` (Eloquent's `SELECT … FOR UPDATE`) inside a database transaction for immediate withdrawal balance deductions.

The flow in `CreateWithdrawUseCase` (immediate path):

```
BEGIN TRANSACTION
  SELECT * FROM accounts WHERE id = ? FOR UPDATE   -- blocks concurrent transactions
  [validate: balance >= requested_amount]
  UPDATE accounts SET balance = balance - amount WHERE id = ?
  INSERT INTO account_withdraws ...
COMMIT
```

The lock is held only for the duration of the transaction (typically < 50 ms). Concurrent requests for the same account queue at the database level and proceed one at a time.

Scheduled withdrawals use the same pattern inside `ProcessScheduledWithdrawsUseCase` when activating each pending record.

## Consequences

**Positive:**
- Atomicity is guaranteed at the database level; no race condition is possible.
- No application-level retry logic needed.
- The lock scope is a single row (`accounts.id`) for a very short time — minimal contention for different accounts, even under high load.
- Composable: the balance deduction and withdrawal insert happen in the same transaction, so a failure after the balance update but before the insert triggers a full rollback.

**Negative / Risks:**
- Concurrent requests for the **same account** will queue at the database level, increasing latency under contention. This is acceptable: per account, legitimate withdrawal frequency is low, and the token-bucket rate limiter (ADR-0003) provides an additional guard.
- Long-running transactions holding the lock can cause lock-wait timeouts for other requests. The transaction scope is kept narrow (balance check + row inserts only; no external I/O inside the transaction).
- MySQL's default `innodb_lock_wait_timeout` (50 s) must be configured appropriately for the expected load profile.
