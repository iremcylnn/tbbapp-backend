# TbbApp backend (map API)

Laravel 12 backend for the TbbApp mobile app's map module. One endpoint:

```
GET /api/map/bootstrap        → {categories: [...], places: [...]} + ETag
```

Send the previous response's `ETag` back as `If-None-Match` to get a free
`304 Not Modified` when nothing changed. See `CLAUDE.md` for the full
architecture and ratified decisions.

## Running

`.env` points at native PostgreSQL on port 5432 (role `tbbapp`, databases
`tbbapp_laravel` + `tbbapp_laravel_test`):

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
CREATE DATABASE tbbapp_laravel OWNER tbbapp;
CREATE DATABASE tbbapp_laravel_test OWNER tbbapp;
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

Runs against real PostgreSQL (`tbbapp_laravel_test`) — the
decimal-serialization and trigger behaviors don't exist on sqlite. Tests
that need the database skip themselves cleanly while it's unreachable.
