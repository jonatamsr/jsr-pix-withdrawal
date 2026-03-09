# 1. Hexagonal Architecture (Ports & Adapters)


Date: 2026-03-05

## Status

Accepted

## Context

A PIX withdrawal service involves multiple infrastructure concerns: relational persistence (MySQL), cache and rate-limit state (Redis), email delivery (SMTP), and distributed tracing (OpenTelemetry). Without an explicit architectural boundary, business rules become entangled with framework and infrastructure code, making the domain logic hard to understand, test, and evolve.

Concerns that motivated the choice of an explicit layering strategy:

1. **Testability** — balance-deduction logic, scheduling rules, and value-object validation must be exercisable with plain PHPUnit, without spinning up MySQL, Redis, or a Swoole server.
2. **Replaceability** — the initial Eloquent ORM and Symfony Mailer adapters must be replaceable (e.g., a different mailer or a different ORM) without touching domain rules.
3. **Clarity of ownership** — every class must have an unambiguous home (domain, application, infrastructure) so that contributors can reason about where a change belongs.

## Decision

Adopt **Hexagonal Architecture** (Ports & Adapters) as the structural pattern for all application code.

Layer mapping to this repository:

| Layer | Folder | Dependency rule |
|---|---|---|
| **Domain** | `app/Domain/` | Zero dependencies on Hyperf, Eloquent, or any third-party package. Pure PHP classes: entities, value objects, enums, domain events, exceptions, port interfaces, and strategies. |
| **Application** | `app/Application/` | Depends only on the Domain layer. Contains use cases (orchestrate domain objects via ports), DTOs, and factories. No framework imports. |
| **Controller / Crontab** | `app/Controller/`, `app/Crontab/` | Thin driving-adapter layer. Delegates to use cases; may import Hyperf request/response types but contains no business logic. |
| **Infrastructure** | `app/Infrastructure/` | Implements domain ports. May depend on Hyperf, Eloquent, Symfony Mailer, OpenTelemetry, etc. Never imported by Domain or Application. |
| **Middleware** | `app/Middleware/` | Cross-cutting HTTP adapters (idempotency, rate limiting, request ID). Use domain ports where cross-cutting concerns interact with business rules. |

Port interfaces are declared in `app/Domain/Port/` and bound to their adapter implementations in `config/autoload/dependencies.php` via Hyperf's DI container.

## Consequences

**Positive:**
- The Domain and Application layers have **zero framework dependencies** and are fully unit-testable with plain PHPUnit and Mockery stubs.
- Swapping infrastructure adapters (e.g., replacing Eloquent with Doctrine, or SMTP with SQS) requires only a new adapter class and a DI binding update — no changes to business logic.
- Onboarding contributors is easier: the folder structure immediately communicates what kind of code belongs where.
- Static analysis tools (PHPStan at level 9) can enforce the dependency direction by analysing imports.

**Negative / Risks:**
- More boilerplate than a simple layered or MVC approach: each infrastructure integration requires an interface + an adapter class.
- Developers must consciously avoid importing framework classes into the Domain layer; this rule is not enforced by tooling today (a PHPStan architectural rule or `deptrac` configuration would harden the boundary).
- Use cases that orchestrate multiple ports may feel verbose compared to Active Record patterns where the model both represents data and performs persistence.
