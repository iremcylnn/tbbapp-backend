# CLAUDE.md — TbbApp backend (map API)

<!-- Copy this file to the Laravel project's root as CLAUDE.md. It carries the context
     ratified in the TbbApp (mobile) repo; the source-of-truth planning notes live there
     in BACKEND.md. Keep the two in sync when decisions change. -->

This is the backend for **TbbApp**, the Tekirdağ Büyükşehir Belediyesi mobile app — an intern
project. The mobile app (Expo/React Native, separate repo) is finished and mock-driven; this
project is the real data source that replaces its mocks. The two applications share NOTHING but
the HTTP+JSON contract below. **The map module's API is the focus and first deliverable.**

The developer is learning Laravel and backend development as they build this — explain the
"why" behind framework mechanisms when introducing them; don't assume backend experience.
A human guide (mentor) assigns tasks and specified the schema; their spec wins over
preferences here.

## Stack

- Laravel 12, PostgreSQL 17 (pgAdmin as inspection client only — ALL schema changes go
  through migrations, never pgAdmin clicks).
- API-only for the mobile contract: JSON routes in `routes/api.php`. The one exception is
  an internal admin panel under `/admin/*` (`routes/web.php`) — session-authenticated
  server-rendered Blade pages for moderating new-place requests, feedback, and action logs.
  It reuses the same `config('admin.api_key')` shared secret as `RequireAdminKey` (wrapped
  in a login form, no real admin accounts yet — see `RequireAdminSession`), and calls the
  same services/models the JSON API uses. It never changes the mobile app's JSON contract.
- Dev serving: `php artisan serve`. The Expo app on a physical phone reaches this via the
  machine's LAN IP (`http://192.168.x.x:8000`), NOT localhost — same Wi-Fi, firewall open.

## Schema (guide's spec — follow it, ask before deviating)

- `locations`: `id`, `title`, `province_id` (always 59 = Tekirdağ), `district_id`, `lat`,
  `long`, `status`, `category_id` (FK → `locations_category`), `created_at`, `updated_at`.
- `locations_category`: `id`, `title`, `status`.
- Open items awaiting the guide's answer (don't build unilaterally): a `districts` table
  (`district_id` currently references nothing), a nullable `description` text column on
  `locations` (the app's details sheet displays one), and the feedback/new-place POST
  endpoints + their table (not yet assigned), and a priority/tier column (or tier-per-category)
  for the app's planned zoom-tier marker hierarchy (see the app repo's BACKEND.md).

## Ratified decisions (reasoning lives in the app repo's BACKEND.md)

- **IDs are permanent.** Never re-key a row; rename or deactivate instead. The app caches
  and cross-references by id (category id → marker style lives app-side).
- **`status` is soft-delete/disable.** Never `DELETE` rows the app may have referenced.
  Every read endpoint filters `status = active` (and `province_id = 59`) server-side — the
  app must never receive disabled rows.
- **No delta sync.** Payloads are small (hundreds of rows); endpoints return full result
  sets and the app does `cache = response`. Freshness, if wanted, is HTTP `ETag` /
  `If-None-Match` → `304` (`MAX(updated_at)` is a ready ETag source). Do not build a custom
  lastUpdate protocol.
- **Server is the authority.** The app pre-validates for UX, but every endpoint re-validates
  everything (Laravel validator, 422 on failure) — assume requests may come from curl, not
  the app.

- **Mock-first, source-swappable.** Real data is scarce; routes' JSON shapes are the fixed
  contract, and each route reads through a swappable source (mock array / seeded DB /
  scraped page / credentialed feed) selected by config, never hardcoded in the route.
  Seeders are the mock system. A route must never call an external origin directly — source
  classes own origins.

## The contract (what the app consumes — changing shapes breaks the app)

The app's map module reads these TypeScript shapes (its `MapPlace` / `MapCategory` /
`MapDistrict`); an adapter file app-side maps DB naming → app naming, so the API may return
DB-flavored keys, but the *fields and types* must cover:

- Place: `id` (number), `title`, `lat`, `long` (numbers, not strings — beware Postgres
  decimals serializing as strings in JSON; cast them), `district_id`, `category_id`,
  optionally `description`.
- Category: `id`, `title`. District: `id`, `title` (if the districts table is approved).
- Read endpoints: GET routes returning the active rows of each table as JSON arrays
  (or one combined bootstrap endpoint — either is fine; coordinate with the app's adapter).
- Future POSTs (when assigned): feedback `{kind: complaint|request, description, lat?,
  long?, location_id?}` and new-place `{title, category_id?, description, lat, long}` —
  mirror the app's `MapFeedbackSubmission` / `MapNewPlaceSubmission`.

Known dataset note: an "afet toplanma yerleri" (disaster assembly points) category with
several hundred rows is expected eventually. The app plots dense categories only when
selected, so the API just serves them like any category — but keep per-category filtering
(`?category_id=`) in mind as a cheap future param.

## Conventions

- Schema changes: one migration per change, `up`+`down`, applied with `php artisan migrate`.
  Seed data (the app repo's `src/data/mapPlaces.ts` mock places are the seed source) lives
  in `database/seeders/`.
- Follow the guide's naming (`title`, `lat`, `long`) even where other names are more common.
- Code and comments in English; any user-facing strings (validation messages that could
  surface in the app) in Turkish.
- Never expose PostgreSQL to the network; the app talks only to the Laravel API.
