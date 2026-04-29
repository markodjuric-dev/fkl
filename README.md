# Worker Time App (PHP + MySQL + JS)

Simple app for calculating effective worker time from `test_log_r` events with rules from the task.

## 1) Setup

1. Copy `.env.example` to `.env`.
2. Fill DB credentials in `.env`.
3. Import SQL dump that contains table `test_log_r`:

```bash
mysql -u <user> -p <database_name> < /path/to/dump.sql
```

4. Verify table exists:

```sql
SHOW TABLES LIKE 'test_log_r';
```

## 2) Run locally

Run PHP built-in server from project root:

```bash
php -S 127.0.0.1:8000
```

Open:

- Frontend: `http://127.0.0.1:8000/public/index.html`
- API examples:
    - `http://127.0.0.1:8000/api/index.php?route=daily-workers&date=2026-04-14`
    - `http://127.0.0.1:8000/api/index.php?route=worker-range&worker_id=1001&from=2026-04-10&to=2026-04-14`

## 3) API contract

All responses are JSON:

```json
{
  "success": true,
  "data": [],
  "errors": []
}
```
