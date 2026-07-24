# Backend notes (map middleman) — working scratchpad

Planning notes for the intern-project backend feeding the map module. Nothing here is built yet;
the app side stays untouched until the API exists (wiring = one adapter file + three props, see
"App-side wiring" below). Ratified reasoning graduates to DECISIONS.md when the backend is real.

## Stack (assigned by the guide)

- Laravel 12 + PostgreSQL 17 (pgAdmin as the client).
- First assignment: install both, create the two tables below.

## Schema

### `locations` (the guide's spec)

| column | notes |
|---|---|
| `id` | permanent — see "Ratified: IDs never change" |
| `title` | maps to `MapPlace.name` |
| `province_id` | always 59 (Tekirdağ plate code); API hard-filters on it |
| `district_id` | FK — but see open question 1: no districts table was specified |
| `lat`, `long` | map to `latitude`/`longitude` (`long` is the guide's naming; follow it) |
| `status` | soft enable/disable — API serves only active rows, never `DELETE` |
| `category_id` | FK → `locations_category` |
| `created_at`, `updated_at` | Laravel `$table->timestamps()`; `MAX(updated_at)` is a free ETag source |

### `locations_category` (the guide's spec)

| column | notes |
|---|---|
| `id` | permanent |
| `title` | maps to `MapCategory.name` |
| `status` | soft enable/disable |

Single places table + category lookup is the ratified shape — table-per-category was considered
and rejected (kills cross-category queries, turns "add a category" into a migration, breaks
id uniqueness). If a category ever needs its own fields (assembly-point capacity etc.), the
ladder is: description string → nullable JSON `detay` column → extension table. Don't climb
early.

## Open questions for the guide

1. **No `districts` table specified** — `district_id` points at nothing. Ask whether to create
   one (mirror of `locations_category`: id, title, status) or keep district names app-side.
2. **No `description` column** — `MapPlace.description` feeds the details sheet. Ask to add it
   (nullable text); otherwise the field maps to nothing.
3. Şikayet/Talep + Yeni Yer Ekle POSTs (and their table) are not in the assignment yet —
   presumably the next one. The app's submission seams are ready whenever.

## Ratified decisions (from the planning discussion)

- **Mock-first middleman; scarcity enforces modularity (ratified 2026-07-17).** Data sources
  will mostly be mocks at this scale — so the middleman is built for configurability: API
  routes' JSON shapes are the fixed contract; each route reads through a swappable source
  (mock array / seeded DB / scraped page / credentialed feed) chosen by config
  (`.env` switch), never hardcoded in the route. Seeders are the mock system; source classes
  own origins; routes never call an origin directly. Same seam philosophy as the app's
  `fetchItems` — the app demos on mock pipes and graduates source-by-source without any
  contract change.

- **IDs never change.** Primary keys are permanent promises; rename/deactivate rows, never
  re-key. This is what keeps cached references, feedback rows, and `categoryStyles.ts` honest.
- **No delta sync / lastUpdate handshake.** Considered and rejected: at this payload size
  (hundreds of rows ≈ tens of KB) a full refetch costs less than the machinery deltas require
  (per-row change tracking, tombstones for deletions, client-side merge state). `cache =
  response` has no failure modes. Revisit only at tens-of-thousands of rows or media-heavy
  payloads.
- **One bootstrap fetch + ETag.** `GET /map/bootstrap` (or per-table GETs) returning
  categories + districts + places in one round-trip; HTTP `ETag`/`If-None-Match` gives the
  "skip if unchanged" behavior for free (304, ~0 bytes) — no custom protocol.
- **Districts effectively static, categories admin-editable but rare.** Both still travel in
  the bootstrap payload; a NEW category also needs an app-side `categoryStyles.ts` entry (the
  generic fallback covers the gap until the app updates).
- **Dense layers (afet toplanma yerleri, several hundred points): plot only when the category
  is selected.** Keeps default marker count at the ~120-place level Leaflet handles well
  (DECISIONS.md#leaflet-marker-density). Escalation ladder if more big layers arrive:
  viewport culling → MapLibre engine swap.
- **Zoom-tier hierarchy when NO filter is active (ratified 2026-07-17, not yet built).**
  Browsing state (no category selected): curated importance tiers gate markers by zoom —
  far out shows tier 1 only, zooming in reveals lower tiers. Seeking state (category
  selected): tiers stand aside, the full category always shows. Needs a tier source in the
  data — prefer tier-per-CATEGORY (~10 rows of curation) over tier-per-place (~1,500), with
  an optional per-place override later; ask the guide about a priority/tier column while the
  schema is young. App-side it's one zoom-gated predicate in the engineMarkers memo (zoom
  comes from the settled region); known cost: marker re-push/pop-in when zoom crosses a tier
  threshold — pick thresholds at natural zooms (district/town/street) to keep crossings rare.
  Full-dataset estimate (~1,000–1,500 places realistic, excl. transit stops) makes this +
  category gating the load-bearing overwhelm controls; bus stops entering scope would
  escalate past both to viewport querying (`?bbox=`) + the native engine.

## Deferred until the middleman stands (2026-07-17)

Real-data integrations, deliberately sequenced AFTER Laravel + the two tables exist:

- **Afet toplanma alanları**: seed rows for `locations`, one-time extraction. Source order:
  ask the guide for the municipality's own register (they showed the list — the belediye
  holds it) → the website's map endpoint via network inspection (with the guide's nod;
  beware projected coordinates like EPSG:3857 needing conversion to lat/lng) → public
  fallbacks (e-Devlet per-address query only; a community GitHub JSON dump exists, verify
  recency). Seed as its own category; overlap with parks = two honest rows (different
  registers), keep each register's own coordinates.
- **Nöbetçi eczaneler**: NOT seed data — changes daily. Would be a live proxy route in
  Laravel calling the Tekirdağ Eczacı Odası's credentialed feed (provincial chambers run
  controlled APIs, e.g. Antalya's XML API with per-org credentials + rate limits);
  credentials live server-side only, never in the app. Institutional ask — guide-level
  conversation with the chamber. Third-party scraped APIs exist but are the wrong basis for
  a municipal app.

## App-side wiring (already designed, zero module changes)

- New adapter file outside the module, e.g. `src/data/api/mapApi.ts`:
  `fetchMapPlaces(signal)`, `submitMapFeedback(submission)`, `submitMapNewPlace(submission)`.
  Owns the renames (`title`→`name`, `lat`/`long`→`latitude`/`longitude`) and throws on
  non-2xx — the module's designed faces (offline TbbStateCard, error toast) handle failures.
- `src/pages/harita.tsx` passes them into `TbbMapView`'s `fetchItems` / `onSubmitFeedback` /
  `onSubmitNewPlace` props.
- `useMapItems`' session cache means new server data appears next app launch, not next map
  open — acceptable; make it a DECISIONS.md line when wired.

## Build path + known gotchas

1. `composer create-project laravel/laravel`, PostgreSQL creds in `.env`.
2. Two migrations, two Eloquent models, seed from the mock data in `src/data/mapPlaces.ts`
   (the 123 places are a ready-made seeder).
3. GET routes in `routes/api.php` returning the tables as JSON (`WHERE status = active`).
4. **Phone ≠ localhost**: the Expo device must call the laptop's LAN IP
   (`http://192.168.x.x:8000`), same Wi-Fi, firewall open — the classic first-hour trap.
5. Expect the real time cost in environment setup (PHP/Composer/Postgres on Windows), not in
   the schema or endpoints — the design work is done.
