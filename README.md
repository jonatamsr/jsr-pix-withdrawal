# JSR PIX Withdrawal

A microservice for processing PIX withdrawal operations, built with the Hyperf framework.

## Requirements

- PHP >= 8.1
- Swoole PHP extension >= 5.0 (with `swoole.use_shortname` set to `Off`)
- MySQL
- Redis

## Getting Started

Start the application using Docker:

```bash
docker-compose up
```

Or run directly:

```bash
php bin/hyperf.php start
```

The server starts on port `9501`.

## API Endpoints

- `GET /health` — Health check (MySQL + Redis)
- `POST /account/{accountId}/balance/withdraw` — Create a PIX withdrawal (supports idempotency and rate limiting)

## Running Tests

```bash
composer test
```
