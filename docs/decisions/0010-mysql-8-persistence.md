# 10. MySQL 8.0


Date: 2026-03-05

## Status

Accepted

## Context

The service must durably persist three types of data:

1. **Account** — balance, owner information; subject to concurrent balance updates.
2. **AccountWithdraw** — withdrawal records; must be atomically linked to balance deductions.
3. **AccountWithdrawPix** — PIX-specific data (key type, key value) linked to a withdrawal.

Persistence requirements:
- **ACID transactions** encompassing both balance deduction and withdrawal record creation.
- **Row-level locking** (`SELECT … FOR UPDATE`) to serialise concurrent balance updates (ADR-0005).
- **Relational integrity** — foreign keys between accounts, withdrawals, and PIX data.
- **Migrations** — versioned schema changes managed via code.

## Decision

Use **MySQL 8.0** (InnoDB engine) as the sole persistence store for account and withdrawal data.

- Accessed via `hyperf/database` (Eloquent ORM) with coroutine-aware connection pooling.
- Schema managed with Hyperf Migrations in `migrations/`.
- Three tables: `accounts`, `account_withdraws`, `account_withdraw_pix`.
- InnoDB's row-level locking supports the `SELECT … FOR UPDATE` pattern in ADR-0005.
- Connection pool size is configurable via `DB_POOL_MAX` environment variable.

## Consequences

**Positive:**
- Mature, well-understood technology with extensive tooling (Adminer, DBeaver, mysqldump).
- Hyperf's `hyperf/database` provides coroutine-safe connection pooling — connections are returned to the pool after each coroutine rather than held for the entire request.
- Eloquent ORM reduces boilerplate for CRUD operations while still allowing raw query access for locking.
- Transactional guarantees cover the balance deduction + withdrawal insert atomically.

**Negative / Risks:**
- MySQL is a vertical-scaling-first database; horizontal sharding requires significant architectural change. For the scale of this service, vertical scaling is sufficient.
- Connection pool exhaustion under extreme load can cause coroutines to wait or fail. The pool size must be sized appropriately relative to MySQL's `max_connections` setting.
- Schema migrations are run manually (`make migrate`); there is no automated migration-on-start. CI/CD pipelines must explicitly run migrations before smoke tests.
