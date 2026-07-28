# TbbApp backend

The Laravel 12 API behind **TbbApp**, the Tekirdağ Büyükşehir Belediyesi mobile
app. It serves the map module's data, handles citizen accounts and submissions,
and ships an internal panel for moderating what citizens send in.

The mobile app (Expo/React Native, separate repo) and this backend share
**nothing but the HTTP+JSON contract** documented here. Changing a response
shape breaks the app; adding a field does not.

This README is meant to be the main reference for the project: what exists, how
it behaves, why it is shaped that way, and what is still open.

- Ratified spec and architecture decisions — **this overrules the code**: [`CLAUDE.md`](CLAUDE.md)
- The mobile app's planning notes and contract reasoning: [`BACKEND.md`](BACKEND.md)

---

## Table of contents

1. [Status at a glance](#status-at-a-glance)
2. [Quick start](#quick-start)
3. [Architecture](#architecture)
4. [API reference](#api-reference)
5. [Admin panel](#admin-panel)
6. [Data model](#data-model)
7. [Security posture](#security-posture)
8. [Commands](#commands)
9. [Configuration](#configuration)
10. [Testing](#testing)
11. [Project layout](#project-layout)
12. [Conventions](#conventions)
13. [Open decisions](#open-decisions)
14. [Before production](#before-production)

---

## Status at a glance

| Area | State |
|---|---|
| Map bootstrap endpoint + ETag caching | **Done**, tested |
| Swappable source layer (`database` / `mock`) | **Done**, tested |
| Citizen auth (register, login, logout, me) | **Done**, tested |
| Password reset (6-digit emailed code) | **Done**, tested — mail driver is `log` in dev |
| Feedback submissions + admin listing | **Done**, tested |
| New-place requests + admin approval | **Done**, tested |
| Admin audit log | **Done**, tested |
| Blade admin panel | **Done**, tested |
| OpenStreetMap pharmacy import | **Done**, tested — Süleymanpaşa imported |
| Zoom-tier / priority column | **Not built** — awaiting a decision |
| Per-category filtering (`?category_id=`) | **Not built** — awaiting a decision |
| Redis caching | **Deferred** — the seam exists |
| News module | **Not built** — see [Open decisions](#open-decisions) |

**82 tests, 302 assertions, all passing** against real PostgreSQL.

Verify any of this yourself with `php artisan test`.

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
the mock-first design paying off: the map can be developed and demonstrated
with no infrastructure at all. (Auth and submissions still need a database;
only map *reads* have a mock source.)

### Creating the databases

As a PostgreSQL superuser:

```sql
CREATE ROLE tbbapp LOGIN PASSWORD 'tbbapp_dev_password';
CREATE DATABASE tbbapp      OWNER tbbapp;   -- development
CREATE DATABASE tbbapp_test OWNER tbbapp;   -- phpunit
```

A Docker alternative exists for machines without a native install:
`docker compose up -d db17` (PostgreSQL 17 on host port **5434** — set
`DB_PORT` to match). The same compose file provides pgAdmin on port 5050.

### Serving to a phone (Expo dev)

A physical phone cannot reach `localhost` — it needs the machine's LAN IP:

```bash
composer serve:lan            # php artisan serve --host=0.0.0.0 --port=8000
```

Then from the phone, on the same Wi-Fi: `http://<LAN-IP>:8000/api/map/bootstrap`.

Windows Defender Firewall must allow inbound TCP 8000 (it prompts on first run
— allow for private networks). The LAN IP changes when the DHCP lease renews;
re-check with `ipconfig` if the phone suddenly cannot connect.

---

## Architecture

Most of this codebase is ordinary Laravel. Three decisions are not, and they
explain most of the file layout.

### 1. The ETag is a version key, not a content hash

`GET /api/map/bootstrap` returns `ETag: "map-v106"`. Send it back as
`If-None-Match` and the answer is `304 Not Modified` with an empty body.

The number comes from a single-row `map_version` table. What keeps it accurate
is **PostgreSQL statement-level triggers** on `locations`, `locations_category`
and `districts` — not application code:

```sql
AFTER INSERT OR UPDATE OR DELETE OR TRUNCATE ... FOR EACH STATEMENT
    EXECUTE FUNCTION bump_map_version()
```

Because the counter lives in the database, **no write path can bypass it**:
Eloquent saves, bulk `upsert()`, the seeder, raw SQL, and manual edits in
pgAdmin all bump it identically, inside the same transaction as the write (so a
rollback rolls the version back too). Statement-level means one bump per
statement, no matter how many rows it touched.

The practical consequence: the version is read *before* the payload is built,
so a `304` costs exactly one single-row `SELECT` — no locations query, no
serialization, no hashing. That is the whole reason the design is not a content
hash. At a few hundred places the difference is small; at the several hundred
"afet toplanma yerleri" rows expected later, it is the difference between a
free freshness check and a full table scan on every app launch.

> **This is invisible from PHP.** If the version ever seems to move "by
> itself", the trigger is defined in
> [`database/migrations/2026_07_24_075222_create_map_version_table.php`](database/migrations/2026_07_24_075222_create_map_version_table.php).

**Trade-off, stated honestly:** any write to those three tables bumps one
shared counter, so editing a single category invalidates every client's cached
map. That is correct but coarse. It is the right call while edits are rare
administrative actions; if map editing ever becomes frequent, per-table or
per-resource versions would be the next step.

### 2. Every map read goes through a swappable source

```
App\Sources\LocationSource      (interface: categories, districts, places, version)
├── DatabaseLocationSource      Eloquent, no WHERE clauses
└── MockLocationSource          in-code MapSeedData
```

Which one runs is a config decision (`MAP_SOURCE`), bound as a singleton in
[`AppServiceProvider::register()`](app/Providers/AppServiceProvider.php). Use
sites never name an implementation. Adding a third source — a scraped page, a
credentialed feed from another directorate — means writing one class and
changing one config value, with no controller or service touched.

"Every read" is meant literally. It includes the admin panel's district and
category dropdowns **and the validation rules policing them**, so a form can
never offer an option its own validator would reject. Both read one source.

**One deliberate exception**, worth knowing because it looks like a violation:
validating a submitted `location_id` uses `exists:locations,id` rather than a
source-derived list. Lookup tables hold ~11 rows; `places` is unbounded and
grows with every OSM import. Materialising thousands of ids into an `in:` list
on every feedback POST would be exactly the query-everything cost the ETag
design exists to avoid. One indexed `EXISTS` is the right tool at that
cardinality. The reasoning is recorded in `CLAUDE.md` and in the rule itself.

Write-side tables — feedback, new-place requests, users, audit log — have no
mock variant and read Eloquent directly. That is correct, not an oversight:
there is no "mock feedback" to serve.

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
proves it holds for any source by feeding the service a hand-built stub with
disabled, out-of-province and unsorted rows.

### Request lifecycle, end to end

```
GET /api/map/bootstrap
  │
  ├─ MapBootstrapController
  │     ├─ MapBootstrapService::etag()    → LocationSource::version()  (1 row)
  │     │     └─ If-None-Match matches?   → 304, stop here
  │     └─ MapBootstrapService::payload() → LocationSource::{categories,districts,places}()
  │           └─ filter active + province 59, sort by id, strip to contract fields
  └─ 200 + ETag
```

Everything above the dashed decision is cheap. That is the design.

---

## API reference

All routes are prefixed with `/api`. Errors are **always JSON**, even without an
`Accept: application/json` header — configured in
[`bootstrap/app.php`](bootstrap/app.php), because curl and the app must never
receive an HTML error page. Validation failures are `422` with Turkish
messages.

A health endpoint lives at `GET /up` (Laravel's built-in).

### Map — public read

```http
GET /api/map/bootstrap
If-None-Match: "map-v106"        (optional)
```

One round-trip; the app caches the whole response. There is deliberately **no
delta sync** — payloads are small, the app does `cache = response`, and
freshness is plain HTTP.

`200 OK`

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

`304 Not Modified` — empty body, when `If-None-Match` matches.

`lat`/`long` being **unquoted numbers** is load-bearing: PostgreSQL's PDO
driver returns `decimal` columns as strings, so `Location::casts()` casts them
to float. The tests assert against raw JSON, not the decoded array, so a
regression here cannot pass unnoticed.

### Citizen auth

Sanctum bearer tokens, 30-day expiry. Rate limit: **20 requests / 15 min per
IP**. Response contract mirrors the old Node server so the app needed no
changes.

| Method | Path | Body | Success |
|---|---|---|---|
| `POST` | `/api/auth/register` | `{firstName, lastName, email, password}` | `201` `{token, user}` |
| `POST` | `/api/auth/login` | `{email, password}` | `200` `{token, user}` |
| `POST` | `/api/auth/forgot-password` | `{email}` | `200` always |
| `POST` | `/api/auth/reset-password` | `{email, code, password}` | `200` |
| `GET` | `/api/auth/me` | — (Bearer) | `200` `user` |
| `POST` | `/api/auth/logout` | — (Bearer) | `200` |

```jsonc
// user object, everywhere it appears
{ "id": 1, "firstName": "Ayşe", "lastName": "Yılmaz", "email": "ayse@example.com" }
```

**Validation:** `firstName`/`lastName` max 100, `email` valid + unique,
`password` min 8 characters.

**Failure modes:** wrong credentials → `401 {"message": "Email veya şifre
hatalı"}` — deliberately identical for an unknown email and a wrong password.
Invalid or expired reset code → `400`. Rate limit exceeded → `429`.

Tokens are stored in the database (unlike the old server's JWTs), so **logout
genuinely revokes** rather than merely asking the client to forget. Expired
rows are pruned daily by a scheduled `sanctum:prune-expired --hours=24`.

### Citizen submissions

Bearer token required. Writes rate-limited **20 / 15 min per user** — a
*separate* counter from auth, so failed logins cannot eat the feedback quota.

| Method | Path | Body |
|---|---|---|
| `POST` | `/api/feedback` | `{kind, description, location_id?, lat?, long?}` |
| `GET` | `/api/feedback/mine` | — |
| `POST` | `/api/new-place-requests` | `{title, category_id?, description, lat, long}` |
| `GET` | `/api/new-place-requests/mine` | — |

**Feedback validation:** `kind` ∈ `complaint|request`; `description` required,
max 2000; `location_id` must exist in `locations`; `lat` between −90 and 90;
`long` between −180 and 180.

**New-place validation:** `title` required, max 200; `category_id` must be an
*active* category (checked against the configured source); `description`
required, max 2000; `lat`/`long` required and range-checked.

New-place requests start at `status: "pending"`. Both listings return newest
first.

### Admin

Shared secret in an `x-admin-key` header, compared with `hash_equals`
(constant-time). Missing or wrong key → `401 {"message": "Yetkisiz"}`.

| Method | Path | Notes |
|---|---|---|
| `GET` | `/api/feedback?kind=` | `complaint\|request`; includes submitter name/email |
| `GET` | `/api/new-place-requests?status=` | `pending\|approved\|rejected` |
| `PATCH` | `/api/new-place-requests/{id}` | `{status, district_id, category_id?}` |
| `GET` | `/api/admin/action-logs` | Audit trail, newest first |

**Approval is the most interesting operation in the system.** `PATCH` with
`status: "approved"` runs inside one transaction:

1. **claim** the request with `UPDATE ... WHERE status = 'pending'`;
2. create the real `locations` row from the submission;
3. write the `admin_action_logs` entry.

All three commit together or none do. The claim is the concurrency defense: if
two admins press approve at the same moment, only one `UPDATE` matches a row.
The loser receives `409 {"message": "Bu öneri zaten karara bağlanmış"}` instead
of silently creating a duplicate location.

`district_id` is required on approval and `category_id` must resolve (from the
submission or the request body) — both validated against the configured source,
so a disabled district or category cannot be used. Unknown id → `404`.

Creating that `locations` row fires the Postgres trigger, `map_version` bumps,
the bootstrap ETag rotates, and every phone picks up the new place on its next
check. **Nothing in the application has to remember to invalidate anything.**

---

## Admin panel

Server-rendered Blade pages at `/admin/*` — the one exception to "API-only".

| Path | Purpose |
|---|---|
| `/admin/login` | Shared-key login form |
| `/admin/new-place-requests` | Moderate submissions (approve / reject) |
| `/admin/feedback` | Read citizen feedback |
| `/admin/action-logs` | Audit trail |

It is session-authenticated (no real admin accounts yet) and reuses the same
`config('admin.api_key')` secret as the JSON middleware, wrapped in a login
form. It calls the same services and models the API does, so the two can never
drift, and it never touches the mobile app's JSON contract.

Admin login has its **own** rate limiter (`admin-auth`), separate from `auth`.
A named limiter in Laravel is one shared bucket across every route using it
(keyed as `md5($name.$key)`, with no route component). Without the split, an
admin locked out from the municipality's shared NAT address would consume the
citizen login quota for every user behind that address.

A throttled Blade form gets a form error; a throttled API call gets JSON `429`.
Same limit, right medium for each — both behaviours are covered by tests.

---

## Data model

Guide-specified tables. Their naming wins over framework convention — `title`,
`lat`, `long`, and the singular `locations_category`:

| Table | Columns |
|---|---|
| `locations` | `id`, `title`, `province_id` (always 59), `district_id` FK, `lat`/`long` `decimal(10,7)`, `status`, `category_id` FK, `description?`, `osm_id?` unique, timestamps |
| `locations_category` | `id`, `title`, `status` — **no timestamps**, per spec |
| `districts` | `id`, `title`, `status` — 11 rows, seeded by the migration |
| `map_version` | Single row. Infrastructure, not domain data |

Supporting tables (Phase 2, ported from the old Node server):

| Table | Columns |
|---|---|
| `users` | `id`, `first_name`, `last_name`, `email` unique, `password`, timestamps |
| `password_reset_codes` | `id`, `user_id` FK cascade, `code_hash`, `expires_at`, `used_at?`, `created_at` |
| `feedback_submissions` | `id`, `user_id` FK cascade, `kind` indexed, `description`, `location_id?` FK, `lat?`/`long?`, timestamps |
| `new_place_requests` | `id`, `user_id` FK cascade, `title`, `category_id?` FK, `description`, `lat`/`long`, `status` default `pending` indexed, timestamps |
| `admin_action_logs` | `id`, `action`, `target_type`, `target_id`, `ip_address?`, `metadata` jsonb, `created_at` |
| `personal_access_tokens` | Sanctum's standard table |

Two rules that shape everything:

- **IDs are permanent.** Never re-key a row; rename or deactivate instead. The
  app caches by id, and category id → marker style lives on the app side.
- **`status` is soft-delete.** Never `DELETE` a row the app may have
  referenced; set `status` and let the read filter hide it.

Seed data lives in [`app/Sources/MapSeedData.php`](app/Sources/MapSeedData.php)
— 11 districts, 11 categories, 23 places — and feeds **both** the seeder and
the mock source. One dataset, two sinks, no chance of them disagreeing.

> Editing that dataset requires bumping `MapSeedData::VERSION`, because nothing
> bumps the mock ETag automatically and a stale `304` would tell clients never
> to ask again. `MapSeedDataVersionTest` enforces this with a fingerprint and
> prints the exact values to paste when it fails. It is a deliberate small
> chore: the human step that replaces the trigger the mock source lacks.

---

## Security posture

Worth reviewing as a whole, since much of it is invisible in the route list.

| Concern | Measure |
|---|---|
| Password storage | bcrypt, 12 rounds, via Eloquent's `hashed` cast |
| Session tokens | Sanctum, DB-backed, 30-day expiry, revoked on logout, pruned daily |
| User enumeration (login) | Unknown email still runs a bcrypt check against a dummy hash, so timing matches |
| User enumeration (reset) | `forgot-password` returns the same message and status whether or not the email exists; mail failures are logged, never surfaced |
| Reset code theft | Codes stored hashed; 15-minute TTL; issuing a new code invalidates unexpired ones |
| Admin key comparison | `hash_equals` — constant-time, no early exit on first differing character |
| Brute force | Three separate named limiters (`auth`, `admin-auth`, `public-write`), 20 per 15 min each |
| Approval race (TOCTOU) | Atomic claim: `UPDATE ... WHERE status = 'pending'`, loser gets `409` |
| Mass assignment | FormRequests + explicit `fillable`; controllers never pass raw request input |
| Disabled rows leaking | Enforced centrally in `MapBootstrapService`, not per-source |
| Error information leakage | JSON errors only; `APP_DEBUG=false` required in production |
| Database exposure | **Never expose PostgreSQL to the network.** The app talks only to this API |

The single weakest point today is the **shared admin key**: one secret, no
identities, no revocation short of rotating it, and the audit log records an IP
rather than a person. It was ported deliberately from the old server to keep
Phase 2 shippable. `RequireAdminKey` and `RequireAdminSession` are the two
seams to replace when real admin accounts are assigned — nothing else needs to
change.

---

## Commands

```bash
php artisan migrate --seed              # schema + canonical dataset (idempotent)
php artisan test                        # full suite
php artisan serve                       # dev server, localhost only
composer serve:lan                      # dev server, reachable from a phone
php artisan schedule:work               # runs the daily expired-token prune

php artisan map:import-pharmacies       # import pharmacies from OpenStreetMap
  --district=Süleymanpaşa               #   which ilçe (default: Süleymanpaşa)
  --dry-run                             #   show what would happen, write nothing

php vendor/bin/pint                     # format code
```

The OSM importer upserts on `osm_id`, so re-running it refreshes existing rows
instead of duplicating them — and it will **not** resurrect a location an admin
has deliberately disabled. Always try `--dry-run` first.

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
| `ADMIN_API_KEY` | `dev-admin-key` | Shared admin secret. **Production needs a long random value** |
| `DB_*` | `127.0.0.1:5432`, `tbbapp` | PostgreSQL connection |
| `MAIL_MAILER` | `log` | Reset codes land in `storage/logs/laravel.log` in dev |
| `APP_LOCALE` | `tr` | User-facing validation messages are Turkish |
| `APP_DEBUG` | `true` | **Must be `false` in production** |

`env()` is read **only inside `config/`**. Application code always reads
`config('...')`, because `php artisan config:cache` in production makes `env()`
return `null` everywhere else — a failure mode that appears only after
deployment, which is exactly when you least want to meet it.

The `.env.example` values are deliberately identical to `.env`: these are
dev-only credentials with no secret worth protecting, so a fresh clone runs
after one `cp`.

---

## Testing

```bash
php artisan test                                   # 82 tests, 302 assertions
php artisan test --filter=MapBootstrapDatabase     # one file
```

The suite runs against **real PostgreSQL** (`tbbapp_test`, configured in
`phpunit.xml`), not sqlite — deliberately. The two things most likely to break
the contract, decimal-to-string serialization and the version triggers, do not
exist on sqlite, so a sqlite suite would pass vacuously while the real API
returned `"lat": "40.9778000"` to the app.

Tests needing the database extend `PostgresTestCase`, which **skips** cleanly
when PostgreSQL is unreachable rather than erroring, so the rest of the suite
stays usable on a machine without it.

Worth reading as documentation of the design in executable form:

| File | What it proves |
|---|---|
| `Feature/MapVersionTest.php` | Triggers fire for Eloquent, bulk updates and raw SQL — one bump per statement |
| `Feature/MapBootstrapDatabaseTest.php` | Disabled/out-of-province rows never leave; floats stay floats; the 304 path queries only `map_version` |
| `Feature/MapBootstrapMockTest.php` | Full payload with the database empty |
| `Unit/MapBootstrapServiceTest.php` | The invariants hold for *any* source, via a stub |
| `Feature/AuthTest.php`, `PasswordResetTest.php` | Token lifecycle; enumeration resistance; code expiry and single use |
| `Feature/NewPlaceRequestTest.php` | Approval creates a location, is atomic, and the approved place appears in the bootstrap |
| `Feature/AdminPanelTest.php` | Dropdowns read the configured source; a disabled category is neither offered nor accepted; limiters are isolated |
| `Feature/PharmacyImportTest.php` | Re-import updates in place and respects admin disabling |

CI ([`.github/workflows/laravel-ci.yml`](.github/workflows/laravel-ci.yml))
runs the same suite against a `postgres:17` service on every push and pull
request to `master`, plus a migration smoke test.

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

database/migrations/    one migration per change, all with a working down()
routes/api.php          the mobile contract
routes/web.php          the /admin Blade panel only
tests/                  Feature/ + Unit/
```

Controllers stay thin: validate, delegate, respond. Anything multi-step lives
in a service, which is why `NewPlaceApprovalService` and `PasswordResetService`
exist as separate classes rather than as controller methods.

---

## Conventions

- One migration per change, with a working `down()`. **All** schema changes go
  through migrations — pgAdmin is an inspection client only, never a place to
  click a column into existence.
- Code and comments in English. User-facing strings that could surface in the
  app (validation messages, error text) in Turkish.
- Follow the guide's naming (`title`, `lat`, `long`) even where another name
  would be more idiomatic.
- Formatting: `php vendor/bin/pint`.
- Comments explain *why*, not *what*. Several non-obvious decisions in this
  codebase are recorded only in a docblock above the code they justify.

---

## Open decisions

Not built, deliberately. These need a decision before anyone starts:

- **Priority / tier column** for the app's planned zoom-tier marker hierarchy
  (see the app repo's `BACKEND.md`). Open question: one tier per location, or
  one per category?
- **Per-category filtering** (`?category_id=`). Cheap to add. It becomes worth
  having when the "afet toplanma yerleri" dataset (several hundred rows)
  arrives, since the app plots dense categories only when selected.
- **Redis caching.** Deferred, not forgotten: `LocationSource::version()` is
  the seam where a Redis key replaces the `map_version` row, and nothing else
  changes.
- **News module.** Investigated in July 2026: the municipality's website
  (`tekirdag.bel.tr`) offers **no RSS, no API and no sitemap**, runs
  CodeIgniter on PHP 5.4, and its article pages carry no structured data. Any
  integration would therefore be a scraper, which by this codebase's rules
  belongs with the OSM importer — a scheduled write-side import into our own
  table, not a live read-through source. Recommendation: ask whoever maintains
  the website for database access or a JSON endpoint before building a scraper
  against the institution's own site.

---

## Before production

Nothing here is a blocker for development, but each is a real gap between the
current state and a deployment:

- [ ] `APP_DEBUG=false` and a generated `APP_KEY`
- [ ] A long random `ADMIN_API_KEY` — the dev value is public in `.env.example`
- [ ] Real admin accounts, replacing the shared key (`RequireAdminKey` and
      `RequireAdminSession` are the seams)
- [ ] A real mail driver — `MAIL_MAILER=log` means password reset codes are
      written to a log file, not delivered
- [ ] HTTPS, and `SESSION_SECURE_COOKIE=true` for the admin panel
- [ ] A cron entry running `php artisan schedule:run` every minute, so the
      token prune actually happens
- [ ] `php artisan config:cache route:cache` in the deploy step
- [ ] Database backups, and confirmation that PostgreSQL is not reachable from
      outside the host
- [ ] Rate limits reviewed against real traffic — 20 per 15 minutes was
      inherited from the old server, not measured
