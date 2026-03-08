# JSR PIX Withdrawal

A microservice for processing **PIX withdrawal** operations built with the **[Hyperf](https://hyperf.io)** framework (PHP 8.4 + Swoole). It supports immediate and scheduled withdrawals, idempotent requests, per-account rate limiting, email notifications, and distributed tracing via OpenTelemetry/Jaeger.

---

## Table of Contents

- [Architecture Overview](#architecture-overview)
- [Prerequisites](#prerequisites)
- [Quick Start](#quick-start)
- [API Documentation](#api-documentation)
- [Service URLs](#service-urls)
- [Technical Decisions](#technical-decisions)
- [Architecture Diagrams](#architecture-diagrams)
  - [Hexagonal Architecture](#hexagonal-architecture)
  - [Context Diagram (C4 Level 1)](#context-diagram-c4-level-1)
  - [Container Diagram (C4 Level 2)](#container-diagram-c4-level-2)
  - [Component Diagram (C4 Level 3)](#component-diagram-c4-level-3)
  - [Sequence Diagrams](#sequence-diagrams)
  - [Entity-Relationship Diagram](#entity-relationship-diagram)
- [Running Tests](#running-tests)
- [Test Coverage](#test-coverage)
- [Observability](#observability)
- [License](#license)

---

## Architecture Overview

The service follows **Hexagonal Architecture** (Ports & Adapters), cleanly separating business rules from infrastructure concerns:

| Layer | Folder | Responsibility |
|---|---|---|
| **Controller** | `app/Controller/` | HTTP entry points; validates input, delegates to use cases |
| **Application** | `app/Application/` | Use cases, DTOs and factories that orchestrate domain logic |
| **Domain** | `app/Domain/` | Entities, Value Objects, Enums, Events, Exceptions, Ports (interfaces) and Strategies — **zero framework dependencies** |
| **Infrastructure** | `app/Infrastructure/` | Adapters that implement domain ports — Eloquent repositories, Symfony Mailer, OpenTelemetry tracer, Hyperf event dispatcher, Token-Bucket rate limiter |
| **Middleware** | `app/Middleware/` | Cross-cutting HTTP middleware (idempotency, rate limiting, request ID) |
| **Crontab** | `app/Crontab/` | Scheduled tasks (process pending withdrawals every minute) |

---

## Prerequisites

| Tool | Minimum Version |
|---|---|
| **Docker** | 20.10+ |
| **Docker Compose** | 2.0+ |

> No local PHP, Swoole, MySQL, or Redis installation is required — everything runs inside containers.

---

## Quick Start

### 1. Clone

```bash
git clone https://github.com/jonatamsr/jsr-pix-withdrawal && cd jsr-pix-withdrawal
```

### 2. Install dependencies (first run only)

Dependencies are installed during the image build. If you need to refresh them:

```bash
docker run --rm -v $(pwd):/app composer install --ignore-platform-reqs
cp .env.example .env
make up
```

Wait a few seconds for MySQL to be healthy. You can verify with:

```bash
docker compose logs -f app
```

### 3. Run database migrations

```bash
docker compose exec app php bin/hyperf.php migrate
```

### 4. Seed test data

Insert a test account with an initial balance:

```bash
docker compose exec app php -r "
    require 'vendor/autoload.php';
    \Hyperf\DbConnection\Db::connection()->insert(
        \"INSERT INTO account (id, name, balance, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())\",
        ['11111111-1111-1111-1111-111111111111', 'Test Account', 1000.00]
    );
    echo \"Seed completed.\n\";
"
```

### 5. Verify

```bash
curl -s http://localhost:9501/health | jq
```

Expected response:

```json
{
  "status": "healthy",
  "checks": {
    "mysql": "ok",
    "redis": "ok"
  }
}
```

---

## API Documentation

### `GET /health` — Health Check

Checks MySQL and Redis connectivity.

**Request:**

```bash
curl -s http://localhost:9501/health | jq
```

**Response `200 OK`:**

```json
{
  "status": "healthy",
  "checks": {
    "mysql": "ok",
    "redis": "ok"
  }
}
```

**Response `503 Service Unavailable`:**

```json
{
  "status": "unhealthy",
  "checks": {
    "mysql": "ok",
    "redis": "fail"
  }
}
```

---

### `POST /account/{accountId}/balance/withdraw` — Create Withdrawal

Creates an immediate or scheduled PIX withdrawal. The endpoint applies **idempotency** (via `X-Idempotency-Key` header) and **per-account rate limiting** (token-bucket, 1 token/s, burst of 10).

**Global middlewares applied:** `RequestIdMiddleware`, `TraceMiddleware`, `ValidationMiddleware`
**Route middlewares:** `IdempotencyMiddleware`, `RateLimitMiddleware`

#### Request Headers

| Header | Required | Description |
|---|---|---|
| `Content-Type` | Yes | `application/json` |
| `X-Idempotency-Key` | No | UUID to guarantee idempotent processing (cached 24 h in Redis) |
| `X-Request-Id` | No | Correlation ID; auto-generated if absent |

#### Request Body

| Field | Type | Required | Description |
|---|---|---|---|
| `method` | `string` | Yes | Withdrawal method. Currently: `pix` |
| `pix.type` | `string` | Yes | PIX key type. Currently: `email` |
| `pix.key` | `string` | Yes | PIX key value (valid email) |
| `amount` | `number` | Yes | Withdrawal amount (min: `0.01`, max 2 decimal places) |
| `schedule` | `string` | No | Schedule date in `Y-m-d H:i` (São Paulo timezone). Omit for immediate |

---

#### Example 1 — Immediate Withdrawal

```bash
curl -s -X POST http://localhost:9501/account/11111111-1111-1111-1111-111111111111/balance/withdraw \
  -H "Content-Type: application/json" \
  -H "X-Idempotency-Key: $(uuidgen)" \
  -d '{
    "method": "pix",
    "pix": {
      "type": "email",
      "key": "user@example.com"
    },
    "amount": 150.00
  }' | jq
```

**Response `201 Created`:**

```json
{
  "id": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
  "account_id": "11111111-1111-1111-1111-111111111111",
  "method": "pix",
  "amount": 150.0,
  "scheduled": false,
  "scheduled_for": null,
  "done": true
}
```

> On success, an email notification is sent to the PIX key address (viewable in [Mailhog](#service-urls)).

---

#### Example 2 — Scheduled Withdrawal

```bash
curl -s -X POST http://localhost:9501/account/11111111-1111-1111-1111-111111111111/balance/withdraw \
  -H "Content-Type: application/json" \
  -H "X-Idempotency-Key: $(uuidgen)" \
  -d '{
    "method": "pix",
    "pix": {
      "type": "email",
      "key": "user@example.com"
    },
    "amount": 50.00,
    "schedule": "2026-03-10 14:30"
  }' | jq
```

**Response `201 Created`:**

```json
{
  "id": "b2c3d4e5-f6a7-8901-bcde-f12345678901",
  "account_id": "11111111-1111-1111-1111-111111111111",
  "method": "pix",
  "amount": 50.0,
  "scheduled": true,
  "scheduled_for": "2026-03-10 14:30:00",
  "done": false
}
```

> The crontab processes scheduled withdrawals every minute once their `scheduled_for` time has passed.

---

#### Example 3 — Idempotent Replay

Sending the same `X-Idempotency-Key` returns the cached response without re-executing the withdrawal:

```bash
IDEMPOTENCY_KEY=$(uuidgen)

# First call — executes
curl -s -X POST http://localhost:9501/account/11111111-1111-1111-1111-111111111111/balance/withdraw \
  -H "Content-Type: application/json" \
  -H "X-Idempotency-Key: $IDEMPOTENCY_KEY" \
  -d '{"method":"pix","pix":{"type":"email","key":"user@example.com"},"amount":10.00}' | jq

# Second call — returns cached response (balance not deducted again)
curl -s -X POST http://localhost:9501/account/11111111-1111-1111-1111-111111111111/balance/withdraw \
  -H "Content-Type: application/json" \
  -H "X-Idempotency-Key: $IDEMPOTENCY_KEY" \
  -d '{"method":"pix","pix":{"type":"email","key":"user@example.com"},"amount":10.00}' | jq
```

---

#### Error Responses

| Status | Code | Scenario |
|---|---|---|
| `400` | `INVALID_UUID` | Invalid account UUID format |
| `400` | `INVALID_WITHDRAW_METHOD` | Unsupported withdrawal method |
| `404` | `ACCOUNT_NOT_FOUND` | Account does not exist |
| `422` | `VALIDATION_ERROR` | Request body fails validation rules |
| `422` | `INSUFFICIENT_BALANCE` | Account balance too low |
| `422` | `INVALID_AMOUNT` | Amount is zero, negative or has >2 decimals |
| `422` | `INVALID_PIX_DATA` | Invalid PIX key type or value |
| `422` | `INVALID_SCHEDULE_DATE` | Schedule date is in the past or wrong format |
| `429` | `RATE_LIMIT_EXCEEDED` | Too many requests (includes `Retry-After` header) |
| `500` | `INTERNAL_ERROR` | Unexpected server error |

**Error response format:**

```json
{
  "code": "INSUFFICIENT_BALANCE",
  "message": "Insufficient balance to complete this withdrawal",
  "details": null
}
```

**Validation error format:**

```json
{
  "code": "VALIDATION_ERROR",
  "message": "The given data was invalid.",
  "details": {
    "pix.key": ["The pix.key field must be a valid email address."]
  }
}
```

---

## Service URLs

| Service | URL | Description |
|---|---|---|
| **API** | [http://localhost:9501](http://localhost:9501) | Hyperf application (Swoole HTTP server) |
| **Mailhog UI** | [http://localhost:8025](http://localhost:8025) | View captured withdrawal notification emails |
| **Jaeger UI** | [http://localhost:16686](http://localhost:16686) | Distributed tracing dashboard |
| **MySQL** | http://localhost:3306 | Database (`root` / `root`) |
| **Redis** | http://localhost:6379 | Cache, rate limiting, idempotency storage |

---

## Technical Decisions

### Why Hexagonal Architecture?

The Domain layer has **zero coupling to Hyperf, Eloquent, or any infrastructure package**. Business rules are expressed through pure PHP: entities with factory methods, value objects with self-validation, and enums. All external dependencies are abstracted behind **port interfaces** (`AccountRepositoryInterface`, `EventDispatcherInterface`, etc.) and wired via Hyperf's DI container in `config/autoload/dependencies.php`. This makes the domain fully testable in isolation and allows swapping infrastructure adapters without touching business logic.

### Why Token-Bucket Rate Limiting per Account?

A token-bucket algorithm (Redis-backed via Hyperf's `RateLimitHandler`) limits each account to **1 token/second with a burst capacity of 10**. This prevents a single account from saturating the service while still allowing short traffic bursts, which is more flexible than a fixed-window counter.

### Why Idempotency Middleware?

Financial operations must be safe to retry. The `IdempotencyMiddleware` caches the full HTTP response (status + headers + body) in Redis for **24 hours** keyed by the `X-Idempotency-Key` header. Replayed requests return the cached response without re-executing the use case, preventing duplicate withdrawals caused by network retries or client-side bugs.

### Why Pessimistic Locking (`SELECT ... FOR UPDATE`)?

Immediate withdrawals deduct the account balance inside a database transaction with `lockForUpdate()`. This ensures that concurrent requests for the same account are serialized at the database level, preventing race conditions that could overdraw an account. The lock scope is narrow (single row, single transaction) to minimize contention.

### Why Domain Events + Listeners?

After a withdrawal completes, a `WithdrawCompleted` event is dispatched. The `SendWithdrawNotificationListener` sends an email notification asynchronously. This decouples the core withdrawal flow from notification delivery — if the mailer is down, the withdrawal still succeeds and the error is logged.

### Why Strategy Pattern for Withdrawal Methods?

The `WithdrawMethodFactory` maps method names to strategy classes (`PixWithdrawStrategy`). Adding a new method (e.g., `TED`) requires only a new strategy class and a factory entry — no changes to the use case or controller.

### Why Symfony Mailer with Mailhog?

Symfony Mailer provides a well-tested, PSR-compatible email abstraction. In development, emails are routed to **Mailhog** (SMTP on port 1025, UI on port 8025) so developers can inspect notifications without configuring a real SMTP server.

### Why OpenTelemetry + Jaeger?

All HTTP requests, database queries, Redis operations, and exceptions are automatically traced via the `TraceMiddleware` and exported to Jaeger through the OTLP HTTP protocol. Each log entry includes `trace_id` and `request_id` via the `TraceContextProcessor`, enabling end-to-end correlation between logs and traces.

### Why Swoole?

Swoole transforms PHP into a persistent, event-driven runtime with coroutine support. This eliminates the traditional PHP request lifecycle overhead (boot → handle → teardown) and enables non-blocking I/O for database, Redis and HTTP calls — resulting in significantly higher throughput and lower latency compared to PHP-FPM.

---

## Architecture Diagrams

### Hexagonal Architecture

```mermaid
%%{init: {'theme': 'dark', 'themeVariables': { 'fontFamily': 'ui-sans-serif, system-ui', 'primaryColor': '#1e293b', 'primaryBorderColor': '#334155', 'lineColor': '#94a3b8', 'clusterBkg': '#0f172a', 'clusterBorder': '#334155'}}}%%
flowchart TB
    classDef adapter fill:#450a0a,stroke:#ef4444,stroke-width:2px,color:#fee2e2,rx:6px
    classDef port fill:#411f02,stroke:#f59e0b,stroke-width:2px,stroke-dasharray: 5 5,color:#fef3c7,rx:6px
    classDef usecase fill:#172554,stroke:#3b82f6,stroke-width:2px,color:#dbeafe,rx:6px
    classDef domain fill:#052e16,stroke:#22c55e,stroke-width:2px,color:#dcfce7,rx:8px
    classDef infra fill:#0f172a,stroke:#64748b,stroke-width:2px,color:#f1f5f9,rx:6px

    subgraph DrivingAdapters ["🔌 Driving Adapters"]
        HTTP["fa:fa-globe HTTP Client"]:::adapter
        CRON["fa:fa-clock Crontab Scheduler"]:::adapter
    end

    subgraph AppLayer ["⚙️ Application Layer"]
        UC_CREATE["CreateWithdrawUseCase"]:::usecase
        UC_PROCESS["ProcessScheduledWithdrawsUseCase"]:::usecase
        FACTORY["WithdrawMethodFactory"]:::usecase
    end

    subgraph DomainCore ["💎 Domain Core"]
        ENT_ACC["Account Entity"]:::domain
        ENT_WD["AccountWithdraw Entity"]:::domain
        ENT_PIX["AccountWithdrawPix Entity"]:::domain
        VO["Value Objects<br/>(Money, Uuid, PixKey, ScheduleDate)"]:::domain
        EVENTS["Domain Events<br/>(WithdrawCompleted, etc)"]:::domain
        STRAT["Strategies<br/>(PixWithdrawStrategy)"]:::domain
    end

    subgraph PortsLayer ["🚪 Ports (Interfaces)"]
        P_ACC["AccountRepositoryInterface"]:::port
        P_WD["WithdrawRepositoryInterface"]:::port
        P_EVT["EventDispatcherInterface"]:::port
        P_TX["TransactionManagerInterface"]:::port
        P_RL["RateLimiterInterface"]:::port
    end

    subgraph DrivenAdapters ["🔌 Driven Adapters"]
        REPO_ACC["EloquentAccountRepository"]:::adapter
        REPO_WD["EloquentWithdrawRepository"]:::adapter
        EVT_ADAPTER["HyperfEventAdapter"]:::adapter
        TX_ADAPTER["DbTransactionManager"]:::adapter
        RL_ADAPTER["TokenBucketRateLimiter"]:::adapter
        MAILER["SymfonyMailerService"]:::adapter
        TRACER["OTelTracer Output"]:::adapter
    end

    subgraph Infra ["🏗️ Infrastructure"]
        MYSQL[("fa:fa-database MySQL")]:::infra
        REDIS[("fa:fa-server Redis")]:::infra
        MAILHOG["fa:fa-envelope Mailhog"]:::infra
        JAEGER["fa:fa-search Jaeger Collector"]:::infra
    end

    DrivingAdapters -->|"POST /withdraw"| UC_CREATE
    DrivingAdapters -->|"cron trigger"| UC_PROCESS

    UC_CREATE --> P_ACC & P_WD & P_EVT & P_TX
    UC_CREATE --> FACTORY --> STRAT
    UC_PROCESS --> P_ACC & P_WD & P_EVT & P_TX

    P_ACC --> ENT_ACC
    P_WD --> ENT_WD
    ENT_WD --> VO
    ENT_ACC --> VO
    P_EVT --> EVENTS

    P_ACC -.->|impl| REPO_ACC
    P_WD -.->|impl| REPO_WD
    P_EVT -.->|impl| EVT_ADAPTER
    P_TX -.->|impl| TX_ADAPTER
    P_RL -.->|impl| RL_ADAPTER

    REPO_ACC --> MYSQL
    REPO_WD --> MYSQL
    TX_ADAPTER --> MYSQL
    RL_ADAPTER --> REDIS
    EVT_ADAPTER -->|"WithdrawCompleted"| MAILER --> MAILHOG
    TRACER --> JAEGER
```

---

### Context Diagram (C4 Level 1)

```mermaid
%%{init: {'theme': 'dark', 'themeVariables': { 'fontFamily': 'ui-sans-serif, system-ui', 'primaryColor': '#1e293b', 'primaryBorderColor': '#334155', 'lineColor': '#94a3b8'}}}%%
flowchart LR
    classDef user fill:#082f49,stroke:#0ea5e9,stroke-width:2px,color:#e0f2fe,rx:8px
    classDef system fill:#312e81,stroke:#6366f1,stroke-width:2px,color:#e0e7ff,rx:8px
    classDef ext fill:#1e293b,stroke:#64748b,stroke-width:2px,color:#f8fafc,rx:8px,stroke-dasharray: 5 5

    USER["👤 <b>API Consumer</b><br/><span style='font-size:12px;color:#94a3b8'>System or Developer</span>"]:::user
    SYS["⚙️ <b>JSR PIX Withdrawal</b><br/><span style='font-size:12px;color:#94a3b8'>Saque PIX Microservice</span>"]:::system
    MAIL["📧 <b>Email (Mailhog)</b><br/><span style='font-size:12px;color:#94a3b8'>Notification Target</span>"]:::ext
    JAE["📊 <b>Jaeger</b><br/><span style='font-size:12px;color:#94a3b8'>Distributed Tracing OTLP</span>"]:::ext

    USER == "POST /withdraw<br/><span style='font-size:12px;color:#94a3b8'>(JSON / HTTP)</span>" ==> SYS
    SYS -. "Sends Notification<br/><span style='font-size:12px;color:#94a3b8'>(SMTP)</span>" .-> MAIL
    SYS -. "Exports Traces<br/><span style='font-size:12px;color:#94a3b8'>(OTLP/HTTP)</span>" .-> JAE
```

---

### Container Diagram (C4 Level 2)

```mermaid
%%{init: {'theme': 'dark', 'themeVariables': { 'fontFamily': 'ui-sans-serif, system-ui', 'primaryColor': '#1e293b', 'primaryBorderColor': '#334155', 'lineColor': '#94a3b8'}}}%%
flowchart TB
    classDef user fill:#082f49,stroke:#0ea5e9,stroke-width:2px,color:#e0f2fe,rx:8px
    classDef app fill:#312e81,stroke:#6366f1,stroke-width:2px,color:#e0e7ff,rx:8px
    classDef db fill:#052e16,stroke:#22c55e,stroke-width:2px,color:#dcfce7,rx:8px
    classDef cache fill:#451a03,stroke:#f59e0b,stroke-width:2px,color:#fef3c7,rx:8px
    classDef ext fill:#1e293b,stroke:#64748b,stroke-width:2px,color:#f8fafc,rx:8px,stroke-dasharray: 4 4
    classDef boundary fill:none,stroke:#64748b,stroke-width:2px,stroke-dasharray: 4 4,rx:8px

    USER["👤 <b>API Consumer</b>"]:::user

    subgraph SYSTEM ["📦 JSR PIX Withdrawal System"]
        direction TB
        API["⚙️ <b>Hyperf API Container</b><br/><span style='font-size:12px;color:#94a3b8'>PHP 8.4 + Swoole • Port 9501</span>"]:::app
        CRON["⏱️ <b>Crontab Worker</b><br/><span style='font-size:12px;color:#94a3b8'>Hyperf Crontab scheduler</span>"]:::app
        MYSQL[("fa:fa-database <b>MySQL 8.0</b><br/><span style='font-size:12px;color:#94a3b8'>Accounts, withdrawal data</span>")]:::db
        REDIS[("fa:fa-server <b>Redis 7</b><br/><span style='font-size:12px;color:#94a3b8'>Idempotency & Rate Limit</span>")]:::cache
    end

    MAIL["📧 <b>Mailhog</b><br/><span style='font-size:12px;color:#94a3b8'>SMTP 1025 / UI 8025</span>"]:::ext
    JAE["📊 <b>Jaeger</b><br/><span style='font-size:12px;color:#94a3b8'>OTLP 4318 / UI 16686</span>"]:::ext

    USER == "JSON HTTPS" ==> API
    API ==>|"TCP 3306"| MYSQL
    API ==>|"TCP 6379"| REDIS
    CRON -->|"TCP 3306"| MYSQL
    CRON -->|"TCP 6379"| REDIS

    API -.->|"SMTP 1025"| MAIL
    CRON -.->|"SMTP 1025"| MAIL
    API -.->|"OTLP/HTTP"| JAE

    class SYSTEM boundary
```

---

### Component Diagram (C4 Level 3)

```mermaid
%%{init: {'theme': 'dark', 'themeVariables': { 'fontFamily': 'ui-sans-serif, system-ui', 'primaryColor': '#1e293b', 'primaryBorderColor': '#334155', 'lineColor': '#94a3b8'}}}%%
flowchart TB
    classDef mw fill:#450a0a,stroke:#ef4444,stroke-width:2px,color:#fee2e2,rx:6px
    classDef ctrl fill:#082f49,stroke:#0ea5e9,stroke-width:2px,color:#e0f2fe,rx:6px
    classDef uc fill:#1e1b4b,stroke:#6366f1,stroke-width:2px,color:#e0e7ff,rx:6px
    classDef infra fill:#0f172a,stroke:#64748b,stroke-width:2px,color:#f8fafc,rx:6px
    classDef ext fill:#052e16,stroke:#22c55e,stroke-width:2px,color:#dcfce7,rx:6px,stroke-dasharray: 4 4

    subgraph Middlewares ["🛡️ HTTP Middlewares"]
        direction LR
        MW_REQ["RequestId"]:::mw
        MW_IDEM["Idempotency"]:::mw
        MW_RATE["RateLimit"]:::mw
    end

    subgraph Controllers ["🌐 Rest Controllers"]
        direction LR
        CTRL["AccountWithdrawController"]:::ctrl
        HEALTH["HealthController"]:::ctrl
    end

    subgraph Application ["⚙️ Application Layer"]
        UC_CREATE["CreateWithdrawUseCase"]:::uc
        UC_PROC["ProcessScheduledWithdraws"]:::uc
        FACT["WithdrawMethodFactory<br/>(PixWithdrawStrategy)"]:::uc
    end

    subgraph Infrastructure ["🏗️ Driven Adapters"]
        REPO_ACC["AccountRepository"]:::infra
        REPO_WD["WithdrawRepository"]:::infra
        TX["DbTransactionManager"]:::infra
        EVT["EventDispatcher"]:::infra
        LISTENER["Listeners<br/>(Log, Notification)"]:::infra
        MAILER["SymfonyMailerService"]:::infra
    end

    MYSQL[("fa:fa-database MySQL")]:::ext
    REDIS[("fa:fa-server Redis")]:::ext
    MAILHOG["fa:fa-envelope Mailhog"]:::ext

    Middlewares ==> Controllers
    CTRL ==> UC_CREATE
    CTRL -.-> FACT
    UC_CREATE ==> REPO_ACC & REPO_WD & TX & EVT
    UC_PROC ==> REPO_ACC & REPO_WD & TX & EVT

    EVT -.-> LISTENER
    LISTENER -.-> MAILER

    REPO_ACC & REPO_WD & TX -.-> MYSQL
    MW_IDEM & MW_RATE -.-> REDIS
    MAILER -.->|"SMTP"| MAILHOG
```

---

### Sequence Diagrams

#### Immediate Withdrawal

```mermaid
%%{init: {'theme': 'dark', 'themeVariables': { 'fontFamily': 'ui-sans-serif, system-ui', 'primaryColor': '#1e1b4b', 'primaryTextColor': '#e0e7ff', 'primaryBorderColor': '#6366f1', 'lineColor': '#94a3b8', 'sequenceNumberColor': '#ffffff', 'actorBkg': '#082f49', 'actorBorder': '#0ea5e9', 'actorTextColor': '#e0f2fe', 'labelBoxBkgColor': '#1e293b', 'labelBoxBorderColor': '#475569'}}}%%
sequenceDiagram
    autonumber
    actor Client
    participant MW_ID as IdempotencyMiddleware
    participant MW_RL as RateLimitMiddleware
    participant Ctrl as AccountWithdrawController
    participant UC as CreateWithdrawUseCase
    participant Factory as WithdrawMethodFactory
    participant Strategy as PixWithdrawStrategy
    participant TX as TransactionManager
    participant AccRepo as AccountRepository
    participant WdRepo as WithdrawRepository
    participant EvtDisp as EventDispatcher
    participant Listener as SendNotificationListener
    participant Mailer as SymfonyMailerService
    participant Redis
    participant MySQL

    Client->>MW_ID: POST /account/{id}/balance/withdraw<br/>[X-Idempotency-Key]
    MW_ID->>Redis: GET idempotency:{key}
    Redis-->>MW_ID: null (cache miss)
    MW_ID->>MW_RL: pass through
    MW_RL->>Redis: Token bucket check
    Redis-->>MW_RL: allowed
    MW_RL->>Ctrl: pass through

    Ctrl->>Ctrl: Validate request (FormRequest)
    Ctrl->>UC: execute(CreateWithdrawInput)

    UC->>Factory: create("pix")
    Factory-->>UC: PixWithdrawStrategy
    UC->>Strategy: validateAndBuild(data)
    Strategy-->>UC: PixWithdrawData

    UC->>TX: execute(callback)
    TX->>MySQL: BEGIN TRANSACTION

    TX->>AccRepo: findByIdWithLock(accountId)
    AccRepo->>MySQL: SELECT ... FOR UPDATE
    MySQL-->>AccRepo: Account row
    AccRepo-->>TX: Account entity

    TX->>TX: account.withdraw(amount)

    TX->>AccRepo: save(account)
    AccRepo->>MySQL: UPDATE account SET balance = ...

    TX->>WdRepo: save(withdraw, pixData)
    WdRepo->>MySQL: INSERT INTO account_withdraw ...
    WdRepo->>MySQL: INSERT INTO account_withdraw_pix ...

    TX->>MySQL: COMMIT
    TX-->>UC: [withdraw, account]

    UC->>EvtDisp: dispatch(WithdrawCompleted)
    EvtDisp->>Listener: process(WithdrawCompleted)
    Listener->>Mailer: sendWithdrawCompleted()
    Mailer->>Mailer: Render HTML template
    Mailer-->>Listener: Email sent via SMTP

    UC-->>Ctrl: CreateWithdrawOutput
    Ctrl-->>MW_ID: Response 201

    MW_ID->>Redis: SET idempotency:{key} (TTL 24h)
    MW_ID-->>Client: 201 Created + JSON body
```

#### Scheduled Withdrawal (Creation)

```mermaid
%%{init: {'theme': 'dark', 'themeVariables': { 'fontFamily': 'ui-sans-serif, system-ui', 'primaryColor': '#1e1b4b', 'primaryTextColor': '#e0e7ff', 'primaryBorderColor': '#6366f1', 'lineColor': '#94a3b8', 'sequenceNumberColor': '#ffffff', 'actorBkg': '#082f49', 'actorBorder': '#0ea5e9', 'actorTextColor': '#e0f2fe', 'labelBoxBkgColor': '#1e293b', 'labelBoxBorderColor': '#475569'}}}%%
sequenceDiagram
    autonumber
    actor Client
    participant MW_ID as IdempotencyMiddleware
    participant MW_RL as RateLimitMiddleware
    participant Ctrl as AccountWithdrawController
    participant UC as CreateWithdrawUseCase
    participant Factory as WithdrawMethodFactory
    participant AccRepo as AccountRepository
    participant WdRepo as WithdrawRepository
    participant MySQL

    Client->>MW_ID: POST /withdraw (with schedule field)
    MW_ID->>MW_RL: pass through (cache miss)
    MW_RL->>Ctrl: pass through (rate OK)

    Ctrl->>UC: execute(CreateWithdrawInput)

    UC->>Factory: create("pix")
    UC->>UC: ScheduleDate::fromString("2026-03-10 14:30")<br/>Convert SP → UTC, validate future

    UC->>AccRepo: findById(accountId)
    AccRepo->>MySQL: SELECT ... (no lock)
    MySQL-->>AccRepo: Account row
    AccRepo-->>UC: Account entity (existence check only)

    UC->>UC: AccountWithdraw::createScheduled(...)

    UC->>WdRepo: save(withdraw, pixData)
    WdRepo->>MySQL: INSERT INTO account_withdraw<br/>(scheduled=true, done=false)
    WdRepo->>MySQL: INSERT INTO account_withdraw_pix

    UC-->>Ctrl: CreateWithdrawOutput (done=false)
    Ctrl-->>Client: 201 Created
```

#### Crontab — Process Scheduled Withdrawals

```mermaid
%%{init: {'theme': 'dark', 'themeVariables': { 'fontFamily': 'ui-sans-serif, system-ui', 'primaryColor': '#1e1b4b', 'primaryTextColor': '#e0e7ff', 'primaryBorderColor': '#6366f1', 'lineColor': '#94a3b8', 'sequenceNumberColor': '#ffffff', 'actorBkg': '#082f49', 'actorBorder': '#0ea5e9', 'actorTextColor': '#e0f2fe', 'labelBoxBkgColor': '#1e293b', 'labelBoxBorderColor': '#475569'}}}%%
sequenceDiagram
    autonumber
    participant Cron as ProcessScheduledWithdrawsCrontab
    participant UC as ProcessScheduledWithdrawsUseCase
    participant WdRepo as WithdrawRepository
    participant AccRepo as AccountRepository
    participant TX as TransactionManager
    participant EvtDisp as EventDispatcher
    participant MySQL

    Cron->>UC: execute()

    UC->>WdRepo: findPendingScheduled()
    WdRepo->>MySQL: SELECT ... WHERE scheduled=1<br/>AND done=0 AND error=0<br/>AND scheduled_for <= NOW()
    MySQL-->>WdRepo: [pending rows + PIX relations]
    WdRepo-->>UC: PendingWithdrawal[]

    loop Each pending withdrawal
        UC->>TX: execute(callback)
        TX->>MySQL: BEGIN TRANSACTION

        TX->>AccRepo: findByIdWithLock(accountId)
        AccRepo->>MySQL: SELECT ... FOR UPDATE
        MySQL-->>TX: Account entity

        alt Sufficient balance
            TX->>TX: account.withdraw(amount)
            TX->>TX: withdraw.markAsDone()
            TX->>AccRepo: save(account)
            TX->>WdRepo: save(withdraw)
            TX->>MySQL: COMMIT
            UC->>EvtDisp: dispatch(WithdrawCompleted)
        else Insufficient balance
            TX->>MySQL: ROLLBACK
            UC->>TX: execute(markAsFailed callback)
            TX->>WdRepo: save(withdraw with error)
            TX->>MySQL: COMMIT
            UC->>EvtDisp: dispatch(WithdrawFailed)
        end
    end
```

#### Idempotency Cache Hit

```mermaid
%%{init: {'theme': 'dark', 'themeVariables': { 'fontFamily': 'ui-sans-serif, system-ui', 'primaryColor': '#1e1b4b', 'primaryTextColor': '#e0e7ff', 'primaryBorderColor': '#6366f1', 'lineColor': '#94a3b8', 'sequenceNumberColor': '#ffffff', 'actorBkg': '#082f49', 'actorBorder': '#0ea5e9', 'actorTextColor': '#e0f2fe', 'labelBoxBkgColor': '#1e293b', 'labelBoxBorderColor': '#475569'}}}%%
sequenceDiagram
    autonumber
    actor Client
    participant MW_ID as IdempotencyMiddleware
    participant Redis

    Client->>MW_ID: POST /withdraw<br/>[X-Idempotency-Key: same-key]
    MW_ID->>Redis: GET idempotency:{key}
    Redis-->>MW_ID: cached response (JSON)

    MW_ID->>MW_ID: Reconstruct Response<br/>(status, headers, body)
    MW_ID-->>Client: 201 Created (cached)<br/>No use case executed
```

#### Rate Limit Exceeded

```mermaid
%%{init: {'theme': 'dark', 'themeVariables': { 'fontFamily': 'ui-sans-serif, system-ui', 'primaryColor': '#1e1b4b', 'primaryTextColor': '#e0e7ff', 'primaryBorderColor': '#6366f1', 'lineColor': '#94a3b8', 'sequenceNumberColor': '#ffffff', 'actorBkg': '#082f49', 'actorBorder': '#0ea5e9', 'actorTextColor': '#e0f2fe', 'labelBoxBkgColor': '#1e293b', 'labelBoxBorderColor': '#475569'}}}%%
sequenceDiagram
    autonumber
    actor Client
    participant MW_ID as IdempotencyMiddleware
    participant MW_RL as RateLimitMiddleware
    participant RL as TokenBucketRateLimiter
    participant Handler as RateLimitExceptionHandler
    participant Redis

    Client->>MW_ID: POST /withdraw
    MW_ID->>MW_RL: pass through (cache miss)

    MW_RL->>RL: attempt(bucketKey, 1, 10, 1)
    RL->>Redis: Token bucket consume
    Redis-->>RL: rejected (no tokens)
    RL-->>MW_RL: false

    MW_RL->>MW_RL: throw RateLimitException

    MW_RL->>Handler: handle(RateLimitException)
    Handler-->>Client: 429 Too Many Requests<br/>Retry-After: 1
```

---

### Entity-Relationship Diagram

```mermaid
%%{init: {'theme': 'dark', 'themeVariables': { 'fontFamily': 'ui-sans-serif, system-ui', 'primaryColor': '#052e16', 'primaryBorderColor': '#22c55e', 'lineColor': '#94a3b8', 'tertiaryColor': '#0f172a'}}}%%
erDiagram
    account {
        char(36) id PK "UUID v4"
        varchar(255) name
        decimal(15_2) balance "Current balance"
        datetime created_at
        datetime updated_at
    }

    account_withdraw {
        char(36) id PK "UUID v4"
        char(36) account_id FK "-> account.id"
        varchar(20) method "e.g. pix"
        decimal(15_2) amount
        boolean scheduled "false = immediate"
        datetime scheduled_for "NULL if immediate"
        boolean done "true when processed"
        boolean error "true on failure"
        varchar(255) error_reason "NULL if no error"
        datetime created_at
        datetime updated_at
    }

    account_withdraw_pix {
        char(36) account_withdraw_id PK,FK "-> account_withdraw.id"
        varchar(20) type "e.g. email"
        varchar(255) key "PIX key value"
    }

    account ||--o{ account_withdraw : "has many"
    account_withdraw ||--o| account_withdraw_pix : "has one (if method=pix)"
```

---

## Running Tests

```bash
# All tests
docker compose exec app composer test

# Specific test file
docker compose exec app vendor/bin/co-phpunit --prepend test/bootstrap.php test/Unit/Application/UseCase/CreateWithdrawUseCaseTest.php

# Static analysis
docker compose exec app composer analyse
```

---

## Test Coverage

The project maintains **100% code coverage** across all layers — Application, Controller, Crontab, Domain, Exception, Infrastructure, Middleware, and Request.

![Test Coverage](docs/images/coverage.png)

---

## Observability

### Structured Logging

All logs are written to `stdout` using a human-readable line format and include `request_id` and `trace_id` for correlation (injected by `TraceContextProcessor`):

```
[2026-03-08 03:24:28] default.INFO: Withdraw completed {"withdraw_id":"50369fd4-...","account_id":"550e8400-...","done":true,"scheduled":false} {"request_id":"4a53aa4c-...","trace_id":"ac05bb63c9e2..."}
```

### Distributed Tracing

Browse traces in the [Jaeger UI](http://localhost:16686). Traced operations include:
- HTTP requests (full lifecycle)
- MySQL queries
- Redis commands
- Exceptions

### Email Notifications

Browse captured emails in the [Mailhog UI](http://localhost:8025).

---

## License

[Apache-2.0](LICENSE)
