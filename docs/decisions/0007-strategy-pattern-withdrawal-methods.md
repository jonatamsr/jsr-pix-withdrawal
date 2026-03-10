# 7. Strategy Pattern for Withdrawal Methods


Date: 2026-03-05

## Status

Accepted

## Context

PIX is the initial payment method supported by this service, but the domain explicitly allows for future methods (e.g., TED, DOC, bank transfer). Each method has distinct validation rules:

- **PIX:** requires a key type (`cpf`, `cnpj`, `email`, `phone`, `evp`) and the corresponding key value, validated by format.
- **TED (future):** would require bank code, branch, account number, account type.
- **DOC (future):** similar to TED with different routing rules.

The `CreateWithdrawUseCase` must construct the method-specific data object before persisting a withdrawal. Placement of the validation logic:

| Approach | Notes |
|---|---|
| `if/switch` in use case | Use case grows with each method; adding TED requires editing use case |
| Polymorphism on DTO | DTO type varies; controller must know all method types |
| **Strategy + Factory** | Each method is a strategy class; a factory maps method name → strategy; use case and controller are unchanged when adding methods |

## Decision

Adopt the **Strategy Pattern** with a Factory for withdrawal method handling:

- **`WithdrawMethodStrategyInterface`** (domain port at `app/Domain/Strategy/`) declares: `buildPix(array $data): AccountWithdrawPix` (or the equivalent method for each payment type).
- **`PixWithdrawStrategy`** implements the interface, validates all PIX-specific fields (key type via `PixKeyType` enum, key value format) and returns an `AccountWithdrawPix` value object.
- **`WithdrawMethodFactory`** (application layer at `app/Application/Factory/`) maps `WithdrawMethod` enum values to strategy instances resolved via the DI container.
- The use case calls `$factory->make($method)->build($data)` — it never references `PixWithdrawStrategy` directly.

Adding a new method (e.g., TED) requires:
1. A new `TedWithdrawStrategy` class.
2. A new entry in `WithdrawMethodFactory`.
3. A new `WithdrawMethod::TED` enum case.
4. No changes to `CreateWithdrawUseCase`, controller, or request object.

## Consequences

**Positive:**
- The use case is closed for modification when new payment methods are added (Open/Closed Principle).
- Each strategy is a small, focused class that is independently testable.
- Strategy resolution through the DI container allows strategies to have their own dependencies (e.g., a TED strategy might need a bank lookup service).
- `WithdrawMethodStrategyInterface` as a domain port means the application layer does not depend on concrete strategy implementations.

**Negative / Risks:**
- Slightly more indirection than a simple `match` expression. For a two-method service, the overhead feels disproportionate; the design pays off as methods are added.
- The `WithdrawMethodFactory` must be updated whenever a new method is added. A self-registering strategy pattern (convention over configuration) could remove this step but adds complexity.
