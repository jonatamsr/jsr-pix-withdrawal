# 8. Symfony Mailer + Mailhog for Email Notifications


Date: 2026-03-05

## Status

Accepted

## Context

After a successful withdrawal, the service sends a notification email to the account holder. The service needs:

1. A **reliable, well-tested email abstraction** that supports SMTP.
2. A **development-safe SMTP target** that captures outbox emails without delivering them to real recipients during local development and CI.
3. **Loose coupling** — the use case must not know which mailer library is used.

Options considered:

| Option | Notes |
|---|---|
| PHPMailer | Low-level; no PSR abstraction; less suitable for DI |
| Laravel/Hyperf built-in mailer | Hyperf's `hyperf/mail` wraps SwiftMailer (deprecated) |
| **Symfony Mailer** | Modern, actively maintained; PSR-compatible; rich DSN configuration; official Mailhog / Mailtrap DSN support |
| SendGrid / AWS SES SDK | Vendor-specific; requires internet access in CI |

## Decision

Use **Symfony Mailer** (`symfony/mailer`) with the SMTP transport, abstracted behind `SymfonyMailerService` in `app/Infrastructure/Mail/`.

- The infrastructure adapter receives the `Mailer` instance via DI injection and is bound to a `MailerServiceInterface` port (or consumed directly by notification strategy classes like `PixWithdrawNotificationStrategy`).
- In the local Docker Compose environment, the SMTP DSN points to **Mailhog** (`smtp://mailhog:1025`), which captures all outbound email and exposes an HTTP UI at port 8025 (`localhost:8025`).
- The environment variable `MAIL_DSN` controls the transport, allowing CI to set a null transport and production to point at a real SMTP server or SES/SendGrid DSN with no code change.

## Consequences

**Positive:**
- Symfony Mailer is actively maintained and widely used; documentation and community support are strong.
- DSN-based transport configuration makes it trivial to swap from Mailhog (dev) to SES (production) via a single environment variable.
- Mailhog's web UI allows developers to verify email content and formatting during development.
- No emails are delivered to real addresses during local development or CI.
- The `PixWithdrawNotificationStrategy` + strategy factory pattern (ADR-0007) means the mailer adapter is only referenced from infrastructure; the domain and application layers are unaware of email delivery.

**Negative / Risks:**
- Symfony Mailer is a synchronous call inside `SendWithdrawNotificationListener`. If the SMTP server is slow or unresponsive, it blocks the listener coroutine. For production, the email send should be offloaded to a queue worker or handled asynchronously.
- Email delivery failures are currently logged and silently ignored (the withdrawal has already committed). Undelivered notifications with no alert mechanism could go unnoticed.
