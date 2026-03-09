# 12. Crontab Worker for Scheduled Withdrawal Processing


Date: 2026-03-05

## Status

Accepted

## Context

The service supports **scheduled withdrawals** — withdrawals submitted with a future `scheduled_at` date. These are persisted as `account_withdraws` records with `done = false` and must be processed at the appointed time.

Options for triggering batch processing of pending withdrawals:

| Approach | Description | Notes |
|---|---|---|
| OS-level cron (`crontab -e`) | External cron calls a PHP CLI script | Requires separate process management; script boot cost per invocation; no access to the already-running Swoole connection pool |
| Kubernetes CronJob / ECS Scheduled Task | Container orchestrator runs a job | Heavyweight for a single-service deployment; cold-start cost; separate image lifecycle |
| Message queue consumer + delay | Publish a delayed message when scheduling; consumer fires at the right time | Accurate timing; requires a broker (RabbitMQ/SQS); excessive complexity for the current scope |
| **Hyperf Crontab** | In-process scheduler running on Swoole; fires tasks on a cron expression | Zero cold-start; reuses the same connection pool; defined in code; manageable in the same deployment unit |

## Decision

Use the **Hyperf Crontab** component (`hyperf/crontab`) to schedule an in-process job that runs `ProcessScheduledWithdrawsUseCase` every minute.

- `ProcessScheduledWithdrawsCrontab` (`app/Crontab/`) is annotated with `#[Crontab(rule: '* * * * *')]`, making it execute once per minute inside the running Swoole server.
- On each tick, the use case queries all `account_withdraws` records where `done = false` and `scheduled_at <= now()`, processes each one (balance deduction + status update), and dispatches `WithdrawCompleted` events.
- The crontab runs inside Swoole's coroutine scheduler, so database and Redis access is non-blocking and reuses the existing connection pools.
- Hyperf Crontab registration is declared in `config/autoload/crontab.php`.

## Consequences

**Positive:**
- No additional infrastructure component (no separate container, message broker, or orchestrator integration).
- Reuses the existing MySQL and Redis connection pools — no cold-start penalty.
- The `ProcessScheduledWithdrawsUseCase` is invoked identically whether triggered by the crontab or a future API endpoint; the trigger is fully decoupled from the use case.
- Minute-level granularity is sufficient for scheduled PIX withdrawals (clients choose a date, not a precise time).
- Crontab configuration and logic are part of the same codebase and deployable unit, simplifying operations.

**Negative / Risks:**
- Minute granularity means a withdrawal scheduled for 14:00:30 will not be processed until the 14:01:00 tick. Seconds-level precision would require a different scheduling mechanism.
- If the crontab tick takes longer than 60 seconds to process all pending withdrawals (e.g., hundreds of records), the next tick will overlap. The current implementation does not implement distributed locking for the crontab; under a multi-instance deployment, multiple instances could process the same pending withdrawal concurrently. The pessimistic lock (ADR-0005) prevents double balance deductions, but redundant processing should be addressed with a distributed crontab lock for production multi-instance deployments.
- Crontab failure (unhandled exception inside the job) is caught by Hyperf's crontab runner and logged, but does not retry automatically. A failed tick means that batch of scheduled withdrawals waits until the next minute.
