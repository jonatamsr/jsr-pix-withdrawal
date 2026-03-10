# 6. Domain Events + Listeners for Post-Withdrawal Side Effects


Date: 2026-03-05

## Status

Accepted

## Context

After a withdrawal completes (immediately or when a scheduled withdrawal is processed), the service must send a notification email. Additionally, when a scheduled withdrawal fails during processing, the failure must be logged in a structured way (without swallowing the error silently).

Options for triggering post-completion actions from the use case:

| Approach | Description | Coupling |
|---|---|---|
| Direct call in use case | Use case calls `MailerService` directly | Use case depends on infrastructure; breaks Hexagonal; harder to test |
| Service-layer hooks (callbacks) | Pass a callback/closure into the use case | Unconstrained coupling; not discoverable |
| Observer pattern (in-process) | Use case fires an event; listeners register independently | Loose coupling; extensible; composable |
| **Domain Events + Listeners** | Use case dispatches a domain event via port; infrastructure listeners react | Fully decoupled from infrastructure; port is a domain concept |
| Transactional outbox / message queue | Persist event in DB; separate worker picks up | Strong at-least-once delivery; excessive complexity for this service scope |

## Decision

Adopt **Domain Events with in-process Listeners** using Hyperf's event dispatcher:

1. **Domain events** (`WithdrawCompleted`, `WithdrawFailed`) are defined in `app/Domain/Event/` as pure PHP value objects with no infrastructure dependency.
2. The **`EventDispatcherInterface` port** (in `app/Domain/Port/`) is the only way the use case dispatches events — it knows nothing about Hyperf or listeners.
3. `HyperfEventDispatcherAdapter` (in `app/Infrastructure/Event/`) implements the port and delegates to the Hyperf `EventDispatcherInterface` (PSR-14 compatible).
4. **Listeners** are registered in `config/autoload/listeners.php`:
   - `SendWithdrawNotificationListener` — receives `WithdrawCompleted`, resolves the notification strategy via `WithdrawNotificationStrategyFactory`, sends the email.
   - `LogWithdrawFailedListener` — receives `WithdrawFailed`, logs the failure with structured context.

If the mailer is down, the email listener throws but the withdrawal is already committed. The error is caught by the listener infrastructure and logged; the withdrawal outcome is not affected.

## Consequences

**Positive:**
- The use case (and entire domain/application layer) has no knowledge of emails, logging frameworks, or Hyperf's DI — all post-completion side effects are handled by infrastructure listeners.
- Adding new post-withdrawal actions (e.g., push notification, audit log export) requires only a new listener class and a registration entry — zero changes to use case code.
- Domain events are plain PHP objects; they are trivially testable without mocking any framework component.
- The `EventDispatcherInterface` port enables testing use cases with a simple spy/stub without a full Hyperf test harness.

**Negative / Risks:**
- In-process listeners are **not durable** — if the process crashes after committing the transaction but before the listeners execute, the notification is lost. This is an accepted trade-off for a development/reference service; production services handling real PIX operations should use a transactional outbox or message queue (e.g., SQS, RabbitMQ).
- Listener failures are currently logged and swallowed (fail-safe). If silent notification failures are unacceptable in production, a dead-letter queue or alert mechanism must be added.
