# TbbApp backend

The Laravel 12 API behind **TbbApp**, the Tekirdağ Büyükşehir Belediyesi mobile
app. It serves the map module's data, handles citizen accounts and submissions,
and ships a small internal panel for moderating what citizens send in.

The mobile app (Expo/React Native, separate repo) and this backend share
**nothing but the HTTP+JSON contract** documented below. Changing a response
shape breaks the app; adding a field does not.

- Architecture decisions and their reasoning: [`CLAUDE.md`](CLAUDE.md)
- Ratified specs (these overrule the code): [`backend-CLAUDE.md`](backend-CLAUDE.md), [`BACKEND.md`](BACKEND.md)

---

## Quick start

**Requirements:** PHP 8.2+ (developed on 8.5) with `pdo_pgsql`, Composer 2, and
PostgreSQL 17+ (developed against a native 18.4 install on port 5432).

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

```bash
curl -i localhost:8000/api/map/bootstrap
```

### No PostgreSQL on the machine?

Set `MAP_SOURCE=mock` in `.env`. The map API then serves the full canonical
dataset straight from PHP — no database touched, no migration needed. This is
the mock-first design paying off: you can develop and demo the map without
infrastructure. (Auth and submissions still need a database; only map *reads*
have a mock source.)

### Creating the databases

As a PostgreSQL superuser:

```sql
CREATE ROLE tbbapp LOGIN PASSWORD 'tbbapp_dev_password';
CREATE DATABASE tbbapp      OWNER tbbapp;   -- development
CREATE DATABASE tbbapp_test OWNER tbbapp;   -- phpunit
```

A Docker alternative exists for machines without a native install:
`docker compose up -d db17` (PostgreSQL 17 on host port **5434** — change
`DB_PORT` accordingly).

---

## The three ideas worth understanding

Most of this codebase is ordinary Laravel. Three decisions are not, and they
explain most of the file layout.

### 1. The ETag is a version key, not a content hash

`GET /api/map/bootstrap` returns `ETag: "map-v106"`. Send it back as
`If-None-Match` and you get `304 Not Modified` with an empty body.

The number comes from a single-row `map_version` table. What keeps it accurate
is **PostgreSQL statement-level triggers** on `locations`, `locations_category`
and `districts` — not application code:

```
AFTER INSERT OR UPDATE OR DELETE OR TRUNCATE ... FOR EACH STATEMENT
    EXECUTE FUNCTION bump_map_version()
```

Because the counter lives in the database, **no write path can bypass it**:
Eloquent saves, bulk `upsert()`, the seeder, raw SQL, and manual edits in
pgAdmin all bump it identically, inside the same transaction as the write (so
a rollback rolls the version back too). Statement-level means one bump per
statement no matter how many rows it touched.

The practical consequence: the version is read *before* the payload is built,
so a `304` costs exactly one single-row `SELECT` — no locations query, no
serialization, no hashing. That is the whole reason the design isn't a content
hash.

> **This is invisible from PHP.** If you ever wonder why the version moves "by
> itself", the trigger is defined in
> [`database/migrations/2026_07_24_075222_create_map_version_table.php`](database/migrations/2026_07_24_075222_create_map_version_table.php).

### 2. Every map read goes through a swappable source

```
App\Sources\LocationSource      (interface: categories, districts, places, version)
├── DatabaseLocationSource      Eloquent, no WHERE clauses
└── MockLocationSource          in-code MapSeedData
```

Which one runs is a config decision (`MAP_SOURCE`), bound as a singleton in
[`AppServiceProvider::register()`](app/Providers/AppServiceProvider.php). Use
sites never name an implementation.

"Every read" is meant literally — it includes the admin panel's district and
category dropdowns **and the validation rules policing them**. A form must
never offer an option its own validator would reject, so options and rules read
one source. (Deliberate exception: validating a submitted `location_id` uses
`exists:locations,id`, because places are unbounded.)

Write-side tables — feedback, new-place requests, users, audit log — have no
mock variant and read Eloquent directly. That is correct, not an oversight.

### 3. Filtering lives in the application, never in the sources

Sources are **dumb readers**. They return raw origin rows, `status` and
`province_id` included, and know nothing about business rules.

[`App\Map\MapBootstrapService`](app/Map/MapBootstrapService.php) is the single
owner of every invariant the API promises:

- only `status = 'active'` rows leave the server (the soft-delete contract);
- only `province_id = 59` (Tekirdağ) places leave the server;
- output is sorted by `id`, so identical data always serializes identically —
  an ETag must never vary for unchanged content;
- rows are stripped to contract fields; `status` and `province_id` are
  server-side concerns and are never serialized.

So a new source implementation *cannot* leak a disabled row by forgetting a
filter. The rule exists in exactly one place, and
[`tests/Unit/MapBootstrapServiceTest.php`](tests/Unit/MapBootstrapServiceTest.php)
proves it holds for any source by feeding the service a hand-built stub.

---

## API reference

All routes below are prefixed with `/api`. Errors are always JSON (validation
failures are `422` with Turkish messages).

### Map — public read

```http
GET /api/map/bootstrap
```

One round-trip; the app caches the whole response.

```jsonc
{
  "categories": [{ "id": 1, "title": "Belediye" }],
  "districts":  [{ "id": 1, "title": "Süleymanpaşa" }],
  "places": [{
    "id": 1,
    "title": "Tekirdağ Büyükşehir Belediyesi",
    "district_id": 1,
    "lat": 40.9778,          // numbers, not strings
    "long": 27.5147,
    "category_id": 1,
    "description": "…"       // nullable
  }]
}
```

`lat`/`long` being **unquoted numbers** is load-bearing: PostgreSQL's PDO
driver hands back `decimal` columns as strings, so `Location::casts()` casts
them to float. The tests assert against raw JSON to catch a regression here.

There is deliberately **no delta sync** — payloads are small, the app does
`cache = response`, and freshness is plain HTTP `ETag` / `If-None-Match`.

### Citizen auth

Sanctum bearer tokens, 30-day expiry. Rate limit: **20 requests / 15 min per IP**.

| Method | Path | Body |
|---|---|---|
| `POST` | `/api/auth/register` | `{firstName, lastName, email, password}` |
| `POST` | `/api/auth/login` | `{email, password}` → `{token, user}` |
| `POST` | `/api/auth/forgot-password` | `{email}` → mails a 6-digit code |
| `POST` | `/api/auth/reset-password` | `{email, code, newPassword}` |
| `GET` | `/api/auth/me` | Bearer token |
| `POST` | `/api/auth/logout` | Bearer token |

The reset flow is enumeration-safe: an unknown email gets the same response and
the same timing as a known one. Codes are hashed, expire in 15 minutes, and a
new code invalidates the previous one.

### Citizen submissions

Bearer token required. Writes rate-limited **20 / 15 min per user** — a
*separate* counter from auth, so failed logins can't eat the feedback quota.

| Method | Path | Body |
|---|---|---|
| `POST` | `/api/feedback` | `{kind: complaint\|request, description, location_id?, lat?, long?}` |
| `GET` | `/api/feedback/mine` | — |
| `POST` | `/api/new-place-requests` | `{title, category_id?, description, lat, long}` → starts `pending` |
| `GET` | `/api/new-place-requests/mine` | — |

### Admin

Shared secret in an `x-admin-key` header, compared with `hash_equals`
(`ADMIN_API_KEY` in `.env`).

| Method | Path | Notes |
|---|---|---|
| `GET` | `/api/feedback?kind=` | |
| `GET` | `/api/new-place-requests?status=` | `pending\|approved\|rejected` |
| `PATCH` | `/api/new-place-requests/{id}` | `{status, district_id (on approve), category_id?}` |
| `GET` | `/api/admin/action-logs` | |

Approving a request atomically claims the pending row, creates a real
`locations` row, and writes the audit-log entry — all in one transaction. The
Postgres trigger then bumps `map_version`, so the bootstrap ETag rotates and
every phone picks up the new place on its next check. Nothing has to remember
to invalidate anything.

---

## Admin panel

Server-rendered Blade pages at `/admin/*` — the one exception to "API-only".

```
/admin/login                 shared-key login form
/admin/new-place-requests    moderate submissions (approve / reject)
/admin/feedback              read citizen feedback
/admin/action-logs           audit trail
```

It is session-authenticated (no real admin accounts yet) and reuses the same
`config('admin.api_key')` secret as the JSON middleware, wrapped in a login
form. It calls the same services and models the API does, so it can never drift
from the API's behaviour — and it never touches the mobile app's contract.

Admin login has its **own** rate limiter (`admin-auth`), separate from
`auth`. A named limiter in Laravel is one shared bucket across every route
using it, so without the split, an admin locked out from the municipality's
shared NAT address would consume the citizen login quota for everyone behind
that address.

---

## Data model

Guide-specified tables (their naming wins over convention — `title`, `lat`,
`long`):

| Table | Columns |
|---|---|
| `locations` | `id`, `title`, `province_id` (always 59), `district_id` FK, `lat`, `long` `decimal(10,7)`, `status`, `category_id` FK, `description?`, `osm_id?` unique, timestamps |
| `locations_category` | `id`, `title`, `status` — **no timestamps**, per spec |
| `districts` | `id`, `title`, `status` — 11 rows, seeded by the migration |
| `map_version` | single row; infrastructure, not domain data |

Supporting tables: `users`, `password_reset_codes`, `feedback_submissions`,
`new_place_requests`, `admin_action_logs`, `personal_access_tokens`.

Two rules that shape everything:

- **IDs are permanent.** Never re-key a row; rename or deactivate instead. The
  app caches by id and maps category id → marker style on its side.
- **`status` is soft-delete.** Never `DELETE` a row the app may have
  referenced; set `status` and let the read filter hide it.

Seed data lives in [`app/Sources/MapSeedData.php`](app/Sources/MapSeedData.php)
— 11 districts, 11 categories, 23 places — and feeds **both** the seeder and
the mock source. One dataset, two sinks.

> Editing that dataset requires bumping `MapSeedData::VERSION`, because nothing
> bumps the mock ETag automatically and a stale `304` would tell clients never
> to ask again. `MapSeedDataVersionTest` enforces this with a fingerprint and
> tells you the exact values to paste when it fails.

---

## Commands

```bash
php artisan migrate --seed              # schema + canonical dataset (idempotent)
php artisan map:import-pharmacies       # import pharmacies from OpenStreetMap
  --district=Süleymanpaşa               #   which ilçe (default: Süleymanpaşa)
  --dry-run                             #   show what would happen, write nothing
php artisan schedule:work               # runs the daily expired-token prune
```

The OSM importer upserts on `osm_id`, so re-running it refreshes existing rows
instead of duplicating them — and it will not resurrect a location an admin has
deliberately disabled.

Note where it lives: `App\Osm`, not `App\Sources`. Read paths that serve a
request implement `LocationSource`; a write-side importer that *feeds* the
database has no request to serve, so it gets its own namespace. It still obeys
the rule that matters — the external origin lives in exactly one class
(`OverpassClient`), and nothing else speaks HTTP to it.

---

## Configuration

| Variable | Default | Purpose |
|---|---|---|
| `MAP_SOURCE` | `database` | `database` or `mock` — which `LocationSource` runs |
| `ADMIN_API_KEY` | `dev-admin-key` | Shared admin secret. **Production needs a long random value.** |
| `DB_*` | `127.0.0.1:5432`, `tbbapp` | PostgreSQL connection |
| `MAIL_MAILER` | `log` | Password-reset codes land in `storage/logs/laravel.log` in dev |
| `APP_LOCALE` | `tr` | User-facing validation messages are Turkish |

`env()` is read **only inside `config/`**. Application code always reads
`config('...')`, because `php artisan config:cache` in production makes `env()`
return `null` everywhere else.

The `.env.example` values are deliberately identical to `.env` — these are
dev-only credentials with no secret worth protecting, so a fresh clone runs
after one `cp`.

---

## Tests

```bash
php artisan test                                   # 82 tests, 302 assertions
php artisan test --filter=MapBootstrapDatabase     # one file
```

The suite runs against **real PostgreSQL** (`tbbapp_test`, configured in
`phpunit.xml`), not sqlite — deliberately. The two things most likely to break
the contract, decimal-to-string serialization and the version triggers, don't
exist on sqlite, so a sqlite suite would pass vacuously.

Tests needing the database extend `PostgresTestCase`, which **skips** cleanly
when PostgreSQL is unreachable rather than erroring, so the rest of the suite
stays usable.

Worth reading, as documentation of the design in executable form:

| File | What it proves |
|---|---|
| `Feature/MapVersionTest.php` | Triggers fire for Eloquent, bulk updates, and raw SQL — one bump per statement |
| `Feature/MapBootstrapDatabaseTest.php` | Disabled/out-of-province rows never leave; floats stay floats; the 304 path queries only `map_version` |
| `Feature/MapBootstrapMockTest.php` | Full payload with the database empty |
| `Unit/MapBootstrapServiceTest.php` | The invariants hold for *any* source, via a stub |
| `Feature/AdminPanelTest.php` | Dropdowns read the configured source; a disabled category is neither offered nor accepted |

CI ([`.github/workflows/laravel-ci.yml`](.github/workflows/laravel-ci.yml))
runs the same suite against a `postgres:17` service on every push and pull
request.

---

## Serving to a phone (Expo dev)

A physical phone cannot reach `localhost` — it needs the machine's LAN IP:

```bash
composer serve:lan            # php artisan serve --host=0.0.0.0 --port=8000
```

Then from the phone, on the same Wi-Fi: `http://<LAN-IP>:8000/api/map/bootstrap`.

Windows Defender Firewall must allow inbound TCP 8000 (it prompts on first run
— allow for private networks). Note that the LAN IP changes when DHCP renews
the lease; re-check it with `ipconfig` if the phone suddenly can't connect.

---

## Project layout

```
app/
├── Auth/           PasswordResetService — the multi-step reset flow
├── Console/        artisan commands
├── Http/
│   ├── Controllers/        thin; API controllers at the root, Admin/ for Blade
│   ├── Middleware/         RequireAdminKey (API), RequireAdminSession (panel)
│   └── Requests/           FormRequests — validation + Turkish messages
├── Map/            MapBootstrapService — the serving rules, in one place
├── Models/
├── NewPlaces/      NewPlaceApprovalService — the atomic approve transaction
├── Osm/            OverpassClient + PharmacyImporter (write-side import)
├── Providers/      AppServiceProvider — source binding + rate limiters
└── Sources/        the LocationSource seam + MapSeedData
```

Controllers stay thin; anything multi-step lives in a service.

---

## Conventions

- One migration per change, with a working `down()`. **All** schema changes go
  through migrations — pgAdmin is an inspection client only, never a place to
  click a column into existence.
- Code and comments in English. User-facing strings that could surface in the
  app (validation messages) in Turkish.
- Follow the guide's naming (`title`, `lat`, `long`) even where another name
  would be more idiomatic.
- Formatting: `php vendor/bin/pint`.
- **Never expose PostgreSQL to the network.** The app talks only to this API.

## Not built yet

Awaiting the guide's decision — don't build these unilaterally:

- a priority/tier column for the app's planned zoom-tier marker hierarchy;
- per-category filtering (`?category_id=`);
- Redis caching — when it arrives, `LocationSource::version()` is the seam
  where a Redis key replaces the `map_version` row, and nothing else changes.
