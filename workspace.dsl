workspace "JSR PIX Withdrawal" "Microservice for processing PIX withdrawal operations built with Hyperf (PHP 8.4 + Swoole)." {

    model {

        # ─────────────────────────────────────────────
        # People / External Actors
        # ─────────────────────────────────────────────
        apiConsumer = person "API Consumer" "System or developer that submits PIX withdrawal requests via HTTP." "External"

        # ─────────────────────────────────────────────
        # External Systems
        # ─────────────────────────────────────────────
        mailhog = softwareSystem "Mailhog" "SMTP server that captures outbound emails in development. UI on port 8025, SMTP on port 1025." "External"
        jaeger  = softwareSystem "Jaeger" "Distributed tracing backend. Receives spans via OTLP/HTTP on port 4318; UI on port 16686." "External"

        # ─────────────────────────────────────────────
        # Core System
        # ─────────────────────────────────────────────
        pixWithdrawal = softwareSystem "JSR PIX Withdrawal" "Processes immediate and scheduled PIX withdrawals. Supports idempotency, per-account rate limiting, email notifications and distributed tracing." {

            !docs .
            !decisions docs/decisions

            api = container "Hyperf API" "HTTP server built with PHP 8.4 + Swoole. Exposes REST endpoints on port 9501." "PHP 8.4 / Hyperf / Swoole" "WebApplication" {

                # Middlewares
                requestIdMiddleware   = component "RequestIdMiddleware"    "Generates or propagates a correlation X-Request-Id header for every inbound request."  "Hyperf Middleware"
                idempotencyMiddleware = component "IdempotencyMiddleware"  "Caches the full HTTP response in Redis for 24 h keyed by X-Idempotency-Key, preventing duplicate withdrawal executions." "Hyperf Middleware"
                rateLimitMiddleware   = component "RateLimitMiddleware"    "Enforces per-account token-bucket rate limiting (1 token/s, burst 10) backed by Redis." "Hyperf Middleware"

                # Request Validation
                createWithdrawRequest = component "CreateWithdrawRequest" "FormRequest that validates the withdrawal request body (method, pix data, amount, schedule)." "Hyperf FormRequest"

                # Controllers
                withdrawController = component "AccountWithdrawController" "Validates incoming withdrawal requests and delegates execution to CreateWithdrawUseCase." "Hyperf Controller"
                healthController   = component "HealthController"           "Returns service health status by probing MySQL and Redis connectivity." "Hyperf Controller"

                # Application DTOs
                createWithdrawInput  = component "CreateWithdrawInput"  "Input DTO carrying withdrawal request data to the use case." "Application DTO"
                createWithdrawOutput = component "CreateWithdrawOutput" "Output DTO carrying the withdrawal result from the use case." "Application DTO"

                # Application Use Cases & Factory
                createWithdrawUseCase            = component "CreateWithdrawUseCase"            "Orchestrates the creation of immediate or scheduled PIX withdrawals: validates data, acquires locks, deducts balance, persists records and dispatches domain events." "Application Use Case"
                processScheduledWithdrawsUseCase = component "ProcessScheduledWithdrawsUseCase" "Queries all pending scheduled withdrawals and processes each inside a transaction (deduct balance or mark as failed) and dispatches events." "Application Use Case"
                withdrawMethodFactory            = component "WithdrawMethodFactory"            "Maps a withdrawal method string to the appropriate Strategy implementation (e.g. pix -> PixWithdrawStrategy)." "Application Factory"

                # Domain Strategy
                pixWithdrawStrategy              = component "PixWithdrawStrategy"              "Validates and builds PIX-specific withdrawal data (key type, key value). Implements WithdrawMethodStrategyInterface." "Domain Strategy"

                # Domain Entities
                accountEntity     = component "Account"              "Aggregate root representing a bank account. Enforces balance invariants and performs the deduction via withdraw()." "Domain Entity"
                withdrawEntity    = component "AccountWithdraw"      "Records an individual withdrawal operation (amount, scheduled flag, done flag, error state). Factory methods: createImmediate(), createScheduled()." "Domain Entity"
                pixEntity         = component "AccountWithdrawPix"   "Stores the PIX key type and key value associated with a withdrawal." "Domain Entity"

                # Domain Value Objects
                moneyVO              = component "Money"              "Value object encapsulating a monetary amount; validates non-negative values and up-to-2 decimal places." "Domain Value Object"
                uuidVO               = component "Uuid"               "Value object encapsulating a UUID v4 string with format validation. Supports generate() and fromString()." "Domain Value Object"
                pixKeyVO             = component "PixKey"             "Value object encapsulating a PIX key (type + value) with format validation per type." "Domain Value Object"
                pendingWithdrawalVO  = component "PendingWithdrawal"  "Value object pairing an AccountWithdraw with its optional WithdrawMethodData for scheduled processing." "Domain Value Object"

                # Domain Enums
                withdrawMethodEnum = component "WithdrawMethod" "Enum defining supported withdrawal methods (PIX)." "Domain Enum"
                pixKeyTypeEnum     = component "PixKeyType"     "Enum defining supported PIX key types (EMAIL) with validation logic." "Domain Enum"

                # Domain Events
                domainEvent       = component "DomainEvent"       "Abstract base class for all domain events, carries occurredAt timestamp." "Domain Event"
                withdrawCompleted = component "WithdrawCompleted" "Domain event dispatched after a withdrawal is successfully processed. Carries withdraw, account and method data." "Domain Event"
                withdrawFailed    = component "WithdrawFailed"    "Domain event dispatched when a scheduled withdrawal fails due to insufficient balance or unexpected error." "Domain Event"

                # Domain Exceptions
                businessException            = component "BusinessException"            "Base exception for domain business rule violations." "Domain Exception"
                accountNotFoundException     = component "AccountNotFoundException"     "Thrown when the requested account does not exist." "Domain Exception"
                insufficientBalanceException  = component "InsufficientBalanceException"  "Thrown when account balance is too low for the withdrawal." "Domain Exception"
                invalidAmountException       = component "InvalidAmountException"       "Thrown when the amount is zero, negative or has more than 2 decimal places." "Domain Exception"
                invalidPixDataException      = component "InvalidPixDataException"      "Thrown when PIX key type or value is invalid." "Domain Exception"
                invalidScheduleDateException = component "InvalidScheduleDateException" "Thrown when the schedule date is in the past or has wrong format." "Domain Exception"
                invalidUuidException         = component "InvalidUuidException"         "Thrown when a UUID string has invalid format." "Domain Exception"
                invalidWithdrawMethodException = component "InvalidWithdrawMethodException" "Thrown when the withdrawal method is not supported." "Domain Exception"
                rateLimitException           = component "RateLimitException"           "Thrown when the per-account rate limit is exceeded." "Domain Exception"

                # Ports (Interfaces)
                accountRepositoryPort  = component "AccountRepositoryInterface"  "Port defining persistence operations for Account aggregates (findById, findByIdWithLock, save)." "Domain Port"
                withdrawRepositoryPort = component "WithdrawRepositoryInterface" "Port defining persistence operations for AccountWithdraw records (save, findPendingScheduled)." "Domain Port"
                eventDispatcherPort    = component "EventDispatcherInterface"    "Port for publishing domain events to interested listeners." "Domain Port"
                transactionManagerPort = component "TransactionManagerInterface" "Port for executing a callback atomically inside a database transaction." "Domain Port"
                rateLimiterPort        = component "RateLimiterInterface"        "Port for checking and consuming rate-limit tokens for a given key." "Domain Port"

                # Infrastructure Driven Adapters
                eloquentAccountRepository  = component "EloquentAccountRepository"  "Implements AccountRepositoryInterface using Eloquent ORM with pessimistic locking (SELECT FOR UPDATE)." "Infrastructure Adapter"
                eloquentWithdrawRepository = component "EloquentWithdrawRepository" "Implements WithdrawRepositoryInterface using Eloquent ORM." "Infrastructure Adapter"
                hyperfEventDispatcherAdapter = component "HyperfEventDispatcherAdapter" "Implements EventDispatcherInterface by delegating to Hyperf PSR-14 event dispatcher." "Infrastructure Adapter"
                dbTransactionManager       = component "DbTransactionManager"       "Implements TransactionManagerInterface wrapping Hyperf DB transactions." "Infrastructure Adapter"
                tokenBucketRateLimiter     = component "TokenBucketRateLimiter"     "Implements RateLimiterInterface using a Redis-backed token-bucket algorithm." "Infrastructure Adapter"
                symfonyMailerService       = component "SymfonyMailerService"       "Sends withdrawal notification emails rendered from an HTML template via Symfony Mailer / SMTP." "Infrastructure Adapter"

                # Infrastructure Notification Chain
                withdrawNotificationStrategyFactory = component "WithdrawNotificationStrategyFactory" "Maps a WithdrawMethod enum to the appropriate notification strategy (e.g. PIX -> PixWithdrawNotificationStrategy)." "Infrastructure Adapter"
                pixWithdrawNotificationStrategy     = component "PixWithdrawNotificationStrategy"     "Sends PIX withdrawal notification emails by building AccountWithdrawPix and delegating to SymfonyMailerService." "Infrastructure Adapter"

                # Infrastructure Listeners
                sendNotificationListener   = component "SendWithdrawNotificationListener" "Listens for WithdrawCompleted events and delegates notification to WithdrawNotificationStrategyFactory." "Infrastructure Listener"
                logWithdrawFailedListener  = component "LogWithdrawFailedListener"        "Listens for WithdrawFailed events and logs the failure details." "Infrastructure Listener"
                dbQueryExecutedListener    = component "DbQueryExecutedListener"           "Logs every executed database query for observability." "Infrastructure Listener"
                resumeExitCoordinatorListener = component "ResumeExitCoordinatorListener" "Handles Swoole worker exit coordination for graceful shutdown." "Infrastructure Listener"

                # Exception Handlers
                appExceptionHandler            = component "AppExceptionHandler"            "Catch-all exception handler for unexpected errors, returns 500." "Exception Handler"
                businessExceptionHandler       = component "BusinessExceptionHandler"       "Handles BusinessException subclasses and returns appropriate HTTP error responses." "Exception Handler"
                rateLimitExceptionHandler       = component "RateLimitExceptionHandler"       "Handles RateLimitException and returns 429 with Retry-After header." "Exception Handler"
                validationExceptionHandler     = component "ValidationExceptionHandler"     "Handles validation exceptions and returns 422 with field-level error details." "Exception Handler"

                # Observability
                traceContextProcessor = component "TraceContextProcessor" "Injects trace_id and request_id into every structured log record." "Infrastructure / Monolog Processor"
                otelTracer            = component "OTelTracer"            "Exports OpenTelemetry spans to Jaeger via OTLP/HTTP." "Infrastructure / OpenTelemetry"
            }

            crontab = container "Crontab Worker" "Hyperf Crontab scheduler that runs every minute to process pending scheduled withdrawals." "PHP 8.4 / Hyperf Crontab" "Process"

            mysql = container "MySQL 8.0" "Relational database storing account balances and withdrawal records. Port 3306." "MySQL 8.0" "Database"

            redis = container "Redis 7" "In-memory store used for idempotency response cache and token-bucket rate limiting. Port 6379." "Redis 7" "Cache"
        }

        # ─────────────────────────────────────────────
        # Relationships - System Context (C4 Level 1)
        # ─────────────────────────────────────────────
        apiConsumer   -> pixWithdrawal "Submits withdrawal requests to" "JSON / HTTP"
        pixWithdrawal -> mailhog       "Sends email notifications to" "SMTP"
        pixWithdrawal -> jaeger        "Exports distributed traces to" "OTLP/HTTP"

        # ─────────────────────────────────────────────
        # Relationships - Container (C4 Level 2)
        # ─────────────────────────────────────────────
        apiConsumer -> api     "POST /account/{id}/balance/withdraw" "JSON / HTTP"
        api     -> mysql   "Reads and writes account and withdrawal data" "TCP 3306"
        api     -> redis   "Stores idempotency cache and rate-limit tokens" "TCP 6379"
        api     -> mailhog "Sends withdrawal notification emails" "SMTP 1025"
        api     -> jaeger  "Exports OpenTelemetry spans" "OTLP/HTTP 4318"
        crontab -> mysql   "Reads pending scheduled withdrawals and updates records" "TCP 3306"
        crontab -> redis   "Reads rate-limit tokens" "TCP 6379"
        crontab -> mailhog "Sends withdrawal notification emails" "SMTP 1025"
        crontab -> api     "Triggers scheduled withdrawal processing" "PHP / in-process"
        mysql   -> api     "Returns query results to" "TCP 3306"

        # ─────────────────────────────────────────────
        # Relationships - Component (C4 Level 3)
        # ─────────────────────────────────────────────

        # External actor <-> entry-point components
        apiConsumer           -> idempotencyMiddleware "Sends HTTP requests to" "JSON / HTTP"
        apiConsumer           -> healthController      "GET /health" "HTTP"
        idempotencyMiddleware -> apiConsumer           "Returns HTTP response to" "JSON / HTTP"
        withdrawController    -> apiConsumer           "Returns HTTP response to" "JSON / HTTP"
        rateLimitMiddleware   -> apiConsumer           "Returns 429 Too Many Requests to" "JSON / HTTP"
        appExceptionHandler   -> apiConsumer           "Returns 500 to" "JSON / HTTP"
        validationExceptionHandler -> apiConsumer      "Returns 422 to" "JSON / HTTP"

        # Middleware chain
        requestIdMiddleware   -> idempotencyMiddleware "Passes request to" "PHP"
        idempotencyMiddleware -> rateLimitMiddleware   "Passes request to (on cache miss)" "PHP"
        rateLimitMiddleware   -> withdrawController    "Passes request to (on rate OK)" "PHP"
        rateLimitMiddleware   -> tokenBucketRateLimiter "Checks token availability via" "PHP"
        rateLimitMiddleware   -> redis                 "Token bucket check" "TCP 6379"
        withdrawController    -> idempotencyMiddleware "Returns response to" "PHP"

        # Redis interactions
        idempotencyMiddleware  -> redis "Reads and stores cached responses in" "TCP 6379"
        tokenBucketRateLimiter -> redis "Reads and decrements token-bucket keys in" "TCP 6379"
        redis -> idempotencyMiddleware  "Returns cached response to" "TCP 6379"

        # Controller -> request validation -> use cases
        withdrawController   -> createWithdrawRequest "Validates request body via" "PHP"
        withdrawController   -> createWithdrawUseCase "Delegates withdrawal creation to" "PHP"
        withdrawController   -> withdrawMethodFactory "Resolves method strategy via" "PHP"
        createWithdrawUseCase -> createWithdrawInput   "Receives input via" "PHP"
        createWithdrawUseCase -> createWithdrawOutput  "Returns result via" "PHP"

        # Health controller
        healthController -> mysql  "Probes MySQL connectivity" "TCP 3306"
        healthController -> redis  "Probes Redis connectivity" "TCP 6379"

        # Validation exception handler
        validationExceptionHandler -> createWithdrawRequest "Handles validation errors from" "PHP"

        # Use case -> factory / strategy
        createWithdrawUseCase -> withdrawMethodFactory "Resolves PIX strategy via" "PHP"
        withdrawMethodFactory -> pixWithdrawStrategy   "Instantiates" "PHP"
        pixWithdrawStrategy   -> pixKeyVO              "Validates key using" "PHP"
        pixWithdrawStrategy   -> pixKeyTypeEnum        "Uses" "PHP"
        withdrawMethodFactory -> withdrawMethodEnum    "Uses" "PHP"

        # Use case -> ports
        createWithdrawUseCase            -> accountRepositoryPort  "Reads / writes Account via" "PHP"
        createWithdrawUseCase            -> withdrawRepositoryPort "Persists withdrawal records via" "PHP"
        createWithdrawUseCase            -> transactionManagerPort "Executes in transaction via" "PHP"
        createWithdrawUseCase            -> eventDispatcherPort    "Dispatches domain events via" "PHP"
        processScheduledWithdrawsUseCase -> accountRepositoryPort  "Reads / writes Account via" "PHP"
        processScheduledWithdrawsUseCase -> withdrawRepositoryPort "Reads / updates withdrawals via" "PHP"
        processScheduledWithdrawsUseCase -> transactionManagerPort "Executes in transaction via" "PHP"
        processScheduledWithdrawsUseCase -> eventDispatcherPort    "Dispatches domain events via" "PHP"

        # Use case -> adapters directly (used in dynamic views)
        createWithdrawUseCase -> hyperfEventDispatcherAdapter "Dispatches domain events to" "PHP"
        createWithdrawUseCase -> eloquentAccountRepository    "Reads / writes Account via" "PHP"
        createWithdrawUseCase -> eloquentWithdrawRepository   "Persists withdrawal records via" "PHP"

        # Transaction manager -> repositories (dynamic view steps)
        transactionManagerPort -> eloquentAccountRepository  "Delegates account persistence to" "PHP"
        transactionManagerPort -> eloquentWithdrawRepository "Delegates withdraw persistence to" "PHP"

        # Port implementations
        accountRepositoryPort  -> eloquentAccountRepository    "Implemented by" "PHP"
        withdrawRepositoryPort -> eloquentWithdrawRepository   "Implemented by" "PHP"
        eventDispatcherPort    -> hyperfEventDispatcherAdapter  "Implemented by" "PHP"
        transactionManagerPort -> dbTransactionManager          "Implemented by" "PHP"
        rateLimiterPort        -> tokenBucketRateLimiter        "Implemented by" "PHP"

        # Repository -> MySQL
        eloquentAccountRepository  -> mysql "Executes SELECT / UPDATE queries against" "TCP 3306"
        eloquentWithdrawRepository -> mysql "Executes INSERT / UPDATE queries against" "TCP 3306"
        dbTransactionManager       -> mysql "Manages BEGIN / COMMIT / ROLLBACK on" "TCP 3306"

        # Event / notification chain (actual flow)
        hyperfEventDispatcherAdapter -> sendNotificationListener    "Dispatches WithdrawCompleted to" "PHP"
        hyperfEventDispatcherAdapter -> logWithdrawFailedListener   "Dispatches WithdrawFailed to" "PHP"
        hyperfEventDispatcherAdapter -> dbQueryExecutedListener     "Dispatches DB query events to" "PHP"
        sendNotificationListener     -> withdrawNotificationStrategyFactory "Resolves notification strategy via" "PHP"
        withdrawNotificationStrategyFactory -> pixWithdrawNotificationStrategy "Creates for PIX method" "PHP"
        pixWithdrawNotificationStrategy     -> symfonyMailerService            "Sends email via" "PHP"
        symfonyMailerService                -> mailhog                         "Delivers notification emails via" "SMTP 1025"

        # Domain model relations
        accountRepositoryPort  -> accountEntity     "Produces / consumes" "PHP"
        withdrawRepositoryPort -> withdrawEntity    "Produces / consumes" "PHP"
        withdrawRepositoryPort -> pendingWithdrawalVO "Returns from findPendingScheduled()" "PHP"
        withdrawEntity         -> moneyVO           "Uses" "PHP"
        withdrawEntity         -> uuidVO            "Uses" "PHP"
        withdrawEntity         -> withdrawMethodEnum "Uses" "PHP"
        accountEntity          -> moneyVO           "Uses" "PHP"
        accountEntity          -> uuidVO            "Uses" "PHP"
        pixEntity              -> pixKeyVO          "Uses" "PHP"
        pixKeyVO               -> pixKeyTypeEnum    "Uses" "PHP"
        pendingWithdrawalVO    -> withdrawEntity    "Wraps" "PHP"
        eventDispatcherPort    -> domainEvent       "Dispatches" "PHP"
        withdrawCompleted      -> domainEvent       "Extends" "PHP"
        withdrawFailed         -> domainEvent       "Extends" "PHP"

        # Domain exceptions hierarchy
        accountNotFoundException       -> businessException "Extends" "PHP"
        insufficientBalanceException   -> businessException "Extends" "PHP"
        invalidAmountException         -> businessException "Extends" "PHP"
        invalidPixDataException        -> businessException "Extends" "PHP"
        invalidScheduleDateException   -> businessException "Extends" "PHP"
        invalidUuidException           -> businessException "Extends" "PHP"
        invalidWithdrawMethodException -> businessException "Extends" "PHP"

        # Exception handling
        rateLimitMiddleware       -> rateLimitException   "Throws on limit exceeded" "PHP"
        rateLimitExceptionHandler -> rateLimitException   "Handles" "PHP"
        businessExceptionHandler  -> businessException    "Handles" "PHP"

        # Observability
        traceContextProcessor -> otelTracer "Enriches log records with trace IDs from" "PHP"
        otelTracer -> jaeger "Exports spans via" "OTLP/HTTP 4318"

        # Swoole lifecycle
        resumeExitCoordinatorListener -> hyperfEventDispatcherAdapter "Coordinates graceful shutdown with" "PHP"
    }

    views {

        # C4 Level 1 - System Context
        systemContext pixWithdrawal "SystemContext" {
            include *
            autoLayout lr
            title "JSR PIX Withdrawal - System Context (C4 Level 1)"
            description "Shows the JSR PIX Withdrawal system alongside the people and external systems it interacts with."
        }

        # C4 Level 2 - Container
        container pixWithdrawal "ContainerDiagram" {
            include *
            autoLayout lr
            title "JSR PIX Withdrawal - Container Diagram (C4 Level 2)"
            description "Shows the containers (API server, crontab worker, MySQL and Redis) that make up the JSR PIX Withdrawal system."
        }

        # C4 Level 3 - Component (API container)
        component api "ComponentDiagram" {
            include *
            autoLayout lr 300 150
            title "JSR PIX Withdrawal - Component Diagram (C4 Level 3) - Hyperf API"
            description "Expands the Hyperf API container into its constituent components: middlewares, controllers, use cases, domain model, ports and driven adapters."
        }

        # Dynamic - Immediate Withdrawal
        dynamic api "ImmediateWithdrawal" "Immediate PIX withdrawal - happy path" {
            autoLayout lr
            title "Immediate Withdrawal - Happy Path"
            description "Shows the end-to-end flow for an immediate PIX withdrawal."

            apiConsumer -> idempotencyMiddleware "POST /account/id/balance/withdraw X-Idempotency-Key"
            idempotencyMiddleware -> redis "GET idempotency:key (cache miss)"
            idempotencyMiddleware -> rateLimitMiddleware "Pass through"
            rateLimitMiddleware -> redis "Token bucket check"
            rateLimitMiddleware -> withdrawController "Pass through (rate OK)"
            withdrawController -> createWithdrawUseCase "execute(CreateWithdrawInput)"
            createWithdrawUseCase -> withdrawMethodFactory "create(pix)"
            withdrawMethodFactory -> pixWithdrawStrategy "validateAndBuild(data)"
            createWithdrawUseCase -> transactionManagerPort "execute(callback) - BEGIN TRANSACTION"
            transactionManagerPort -> eloquentAccountRepository "findByIdWithLock(accountId) - SELECT FOR UPDATE"
            eloquentAccountRepository -> mysql "SELECT FOR UPDATE"
            transactionManagerPort -> eloquentAccountRepository "save(account) - UPDATE balance"
            eloquentAccountRepository -> mysql "UPDATE account SET balance"
            transactionManagerPort -> eloquentWithdrawRepository "save(withdraw, pixData)"
            eloquentWithdrawRepository -> mysql "INSERT INTO account_withdraw and account_withdraw_pix"
            createWithdrawUseCase -> hyperfEventDispatcherAdapter "dispatch(WithdrawCompleted)"
            hyperfEventDispatcherAdapter -> sendNotificationListener "process(WithdrawCompleted)"
            sendNotificationListener -> withdrawNotificationStrategyFactory "create(PIX)"
            withdrawNotificationStrategyFactory -> pixWithdrawNotificationStrategy "notify(withdraw, methodData)"
            pixWithdrawNotificationStrategy -> symfonyMailerService "sendWithdrawCompleted()"
            symfonyMailerService -> mailhog "SMTP: deliver notification email"
            withdrawController -> idempotencyMiddleware "Response 201"
            idempotencyMiddleware -> redis "SET idempotency:key TTL 24h"
            idempotencyMiddleware -> apiConsumer "201 Created + JSON body"
        }

        # Dynamic - Scheduled Withdrawal
        dynamic api "ScheduledWithdrawal" "Scheduled PIX withdrawal creation" {
            autoLayout lr
            title "Scheduled Withdrawal - Creation"
            description "Shows the flow for creating a scheduled withdrawal: no balance lock, record persisted with done=false."

            apiConsumer -> idempotencyMiddleware "POST /withdraw (with schedule field)"
            idempotencyMiddleware -> rateLimitMiddleware "Pass through (cache miss)"
            rateLimitMiddleware -> withdrawController "Pass through (rate OK)"
            withdrawController -> createWithdrawUseCase "execute(CreateWithdrawInput)"
            createWithdrawUseCase -> withdrawMethodFactory "create(pix)"
            createWithdrawUseCase -> eloquentAccountRepository "findById(accountId) - existence check only"
            eloquentAccountRepository -> mysql "SELECT account (no lock)"
            createWithdrawUseCase -> eloquentWithdrawRepository "save(withdraw, pixData)"
            eloquentWithdrawRepository -> mysql "INSERT account_withdraw (scheduled=true, done=false)"
            withdrawController -> apiConsumer "201 Created (done=false)"
        }

        # Dynamic - Idempotency Cache Hit
        dynamic api "IdempotencyCacheHit" "Idempotency cache hit - cached response returned" {
            autoLayout lr
            title "Idempotency Cache Hit"
            description "Replayed request returns the previously cached response without executing the withdrawal again."

            apiConsumer -> idempotencyMiddleware "POST /withdraw X-Idempotency-Key: same-key"
            idempotencyMiddleware -> redis "GET idempotency:key"
            redis -> idempotencyMiddleware "Cached response (JSON hit)"
            idempotencyMiddleware -> apiConsumer "201 Created (cached) - no use case executed"
        }

        # Dynamic - Rate Limit Exceeded
        dynamic api "RateLimitExceeded" "Rate limit exceeded - 429 returned" {
            autoLayout lr
            title "Rate Limit Exceeded"
            description "Shows the flow when an account exhausts its rate-limit tokens."

            apiConsumer -> idempotencyMiddleware "POST /withdraw"
            idempotencyMiddleware -> rateLimitMiddleware "Pass through (cache miss)"
            rateLimitMiddleware -> tokenBucketRateLimiter "attempt(bucketKey, 1, 10, 1)"
            tokenBucketRateLimiter -> redis "Token bucket consume - rejected (no tokens)"
            rateLimitMiddleware -> apiConsumer "429 Too Many Requests - Retry-After: 1"
        }

        # Dynamic - Crontab Processing
        dynamic pixWithdrawal "CrontabProcessing" "Crontab processes all pending scheduled withdrawals" {
            autoLayout lr
            title "Crontab - Process Scheduled Withdrawals"
            description "Every minute the crontab triggers ProcessScheduledWithdrawsUseCase which processes pending withdrawals."

            crontab -> api "execute() - trigger ProcessScheduledWithdrawsUseCase"
            api     -> mysql "SELECT pending scheduled withdrawals WHERE scheduled_for <= NOW()"
            mysql   -> api  "Pending withdrawal rows"
            api     -> mysql "BEGIN TRANSACTION - SELECT account FOR UPDATE"
            api     -> mysql "UPDATE account balance / mark withdraw done"
            api     -> mysql "COMMIT"
            api     -> mailhog "SMTP - withdrawal notification email"
        }

        styles {
            element "Person" {
                shape Person
                background #082f49
                color #e0f2fe
            }
            element "External" {
                background #1e293b
                color #f8fafc
                border Dashed
            }
            element "Software System" {
                background #312e81
                color #e0e7ff
            }
            element "WebApplication" {
                background #172554
                color #dbeafe
                shape RoundedBox
            }
            element "Process" {
                background #1e1b4b
                color #e0e7ff
                shape RoundedBox
            }
            element "Database" {
                background #052e16
                color #dcfce7
                shape Cylinder
            }
            element "Cache" {
                background #451a03
                color #fef3c7
                shape Cylinder
            }
            element "Hyperf Middleware" {
                background #450a0a
                color #fee2e2
            }
            element "Hyperf FormRequest" {
                background #450a0a
                color #fee2e2
            }
            element "Hyperf Controller" {
                background #082f49
                color #e0f2fe
            }
            element "Application DTO" {
                background #172554
                color #dbeafe
            }
            element "Application Use Case" {
                background #1e1b4b
                color #e0e7ff
            }
            element "Application Factory" {
                background #1e1b4b
                color #e0e7ff
            }
            element "Domain Strategy" {
                background #052e16
                color #dcfce7
            }
            element "Domain Entity" {
                background #052e16
                color #dcfce7
            }
            element "Domain Value Object" {
                background #052e16
                color #dcfce7
            }
            element "Domain Enum" {
                background #052e16
                color #dcfce7
            }
            element "Domain Event" {
                background #052e16
                color #dcfce7
                border Dashed
            }
            element "Domain Exception" {
                background #052e16
                color #dcfce7
            }
            element "Domain Port" {
                background #411f02
                color #fef3c7
                border Dashed
            }
            element "Infrastructure Adapter" {
                background #0f172a
                color #f1f5f9
            }
            element "Infrastructure Listener" {
                background #0f172a
                color #f1f5f9
            }
            element "Exception Handler" {
                background #0f172a
                color #f1f5f9
            }
            element "Infrastructure / Monolog Processor" {
                background #0f172a
                color #f1f5f9
            }
            element "Infrastructure / OpenTelemetry" {
                background #0f172a
                color #f1f5f9
            }
            element "Hyperf Crontab" {
                background #1e293b
                color #f8fafc
            }
            element "Table" {
                background #052e16
                color #dcfce7
                shape RoundedBox
            }
        }

        themes default
    }

    configuration {
        scope softwareSystem
    }
}

