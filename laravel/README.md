# TbbApp backend (map API)

Laravel 12 backend for the TbbApp mobile app. See `CLAUDE.md` for the full
architecture and ratified decisions.

## API surface

Public read:

```
GET /api/map/bootstrap   → {categories, districts, places} + ETag
```

Send the previous response's `ETag` back as `If-None-Match` to get a free
`304 Not Modified` when nothing changed.

Citizen auth (Sanctum bearer tokens; rate-limited 20 req / 15 min per IP):

```
POST /api/auth/register          {firstName, lastName, email, password}
POST /api/auth/login             {email, password}          → {token, user}
POST /api/auth/forgot-password   {email}                    → mails a 6-digit code
POST /api/auth/reset-password    {email, code, newPassword}
GET  /api/auth/me                (Bearer token)
POST /api/auth/logout            (Bearer token)
```

Citizen submissions (Bearer token; writes rate-limited 20 / 15 min per user):

```
POST /api/feedback               {kind: complaint|request, description, location_id?, lat?, long?}
GET  /api/feedback/mine
POST /api/new-place-requests     {title, category_id?, description, lat, long}   → starts "pending"
GET  /api/new-place-requests/mine
```

Admin (shared `x-admin-key` header — `ADMIN_API_KEY` in `.env`):

```
GET   /api/feedback?kind=
GET   /api/new-place-requests?status=pending|approved|rejected
PATCH /api/new-place-requests/{id}   {status: approved|rejected, district_id (on approve), category_id?}
GET   /api/admin/action-logs
```

Approving a request creates a real `locations` row in the same transaction as
the audit-log entry; the Postgres trigger bumps `map_version`, so the
bootstrap ETag rotates automatically.

## Running

`.env` points at native PostgreSQL on port 5432 (role `tbbapp`, databases
`tbbapp` + `tbbapp_test`):

```bash
php artisan migrate --seed
php artisan serve
curl -i localhost:8000/api/map/bootstrap
```

No PostgreSQL on the machine? Set `MAP_SOURCE=mock` in `.env` and the API
serves the full canonical dataset from code — no database touched. The
docker `db17` service (port 5434) is a third option for Docker machines.

Fresh database setup (as superuser):

```sql
CREATE ROLE tbbapp LOGIN PASSWORD 'tbbapp_dev_password';
CREATE DATABASE tbbapp OWNER tbbapp;
CREATE DATABASE tbbapp_test OWNER tbbapp;
```

## Serving to a phone (Expo dev)

The phone can't reach `localhost` — it needs the machine's LAN IP:

```bash
composer serve:lan     # php artisan serve --host=0.0.0.0 --port=8000
```

Phone (same Wi-Fi): `http://<LAN-IP>:8000/api/map/bootstrap`. Windows
Defender Firewall must allow inbound TCP 8000 (it prompts on first run;
allow for private networks).

## Tests

```bash
php artisan test
```

Runs against real PostgreSQL (`tbbapp_test`) — the
decimal-serialization and trigger behaviors don't exist on sqlite. Tests
that need the database skip themselves cleanly while it's unreachable.
