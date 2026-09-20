# Phase 3C.5 — Public Catalog Discovery (Search / Filter / Sort) — Master Plan

> **Status:** GATE A APPROVED 2026-09-20 — backend discovery core + SEO delivery implemented and fully verified (SQLite + MySQL 8.4, Pint, diff check, initial-HTML SEO contract) and committed. AWAITING GATE B AUTHORIZATION.
> **Mode note:** This file is the master/architecture plan. §AO kickoff corrections below have been applied.

## Goal

Add public catalog **discovery** to the `/katalog` page: **Search** (`q`), **Filter** (SNI / TKDN / availability), and **Sort** (strict allowlist), driven entirely by the URL as the source of truth, with browser Back/Forward support and page-reset on discovery change. **Category is explicitly deferred by design.**

## Scope decisions (locked 2026-09-01)

1. **Category is DEFERRED BY DESIGN, not a missing implementation.** It is not in 3C.5 scope.
2. **No category schema, migration, model, relation, seed, or UI in Phase 3C.5.** The only "category" concept in the docs (LKPP-02 category-gap matrix) is an *internal* procurement-compliance reporting concern (Module 9.6), not public discovery. Category remains a documented future **migration candidate** only.
3. **Existing `/api/v1/products` contract must not be silently broken or changed without an explicit API-evolution decision.** 3C.5 touches only the web `/katalog` path. The public API and `ProductResource` are left untouched.

## Architecture

Keep `CatalogController` thin; encapsulate discovery in small, testable units (matches repo conventions: thin controllers, Enums, scopes):

- `app/Enums/CatalogSort.php` — backed enum; STRICT allowlist mapping `sort` → `[column, direction, tiebreak]`; `fromRequest()` returns default for unknown values (never interpolate raw input into SQL).
- **Correction (§AO #1/#5): no standalone `ProductFilter` value object.** Normalization is a private `CatalogController::normalizeDiscovery(Request)` helper returning a plain array `{q, sni, tkdn_min, availability, sort}`. `description` is **NOT** searchable (title+sku only).
- Model scopes on `Product`: `scopeSearch($q)` (grouped `where title like OR sku like %term%`), `scopeSni()`, `scopeTkdnMin($v)`, `scopeAvailable()` (`stock > 0`).
- `CatalogController::index` — call `normalizeDiscovery()`, apply scopes + allowlisted order, `paginate(12)`, then the **unchanged 9-key whitelist projection**.
- Confirm the public payload allowlist remains **exactly** `{id, sku, title, description, price, tkdn, hasSni, warrantyInfo, available}`; `stock` never serialized.

Rejected: Form Request validation (would 302 on garbage discovery input, fighting "URL = source of truth"); manual normalize/clamp (ignore invalid dims) is correct.

## Tech Stack

Laravel 13, PHP 8.3, Eloquent, Inertia + Vue 3 + Vite, Tailwind 4. Tests: PHPUnit Feature (SQLite + MySQL 8.4) + live MCP-browser E2E. No JS test runner.

## Global Constraints

- **No category schema/migration/model/UI in 3C.5.**
- **No change to `/api/v1/products` or `ProductResource`.**
- **No speculative index migration** in 3C.5 (per Phase B report line 56: `%term%` LIKE is not B-tree indexable and is acceptable for catalog search — do not add a B-tree index for it). Indexes return only as explicit MIGRATION CANDIDATES (see §Query/performance).
- No SSR; SSR is not enabled and no SSR claim is made.
- Locked brand palette, Instrument Sans, `transition-colors` only (no `transition:all`), focus `focus-visible:ring-2 focus-visible:ring-line-strong focus-visible:ring-offset-2`.
- Anti-slop: procurement-native, information-first UI; no mega filter sidebars, no endless pills, no giant ecommerce search bar, no autocomplete/suggestions, no fabricated emptiness copy.
- No commit, no push, no deployment during plan; GATE A implementation requires separate explicit approval.

## Capability verdicts (evidence-based)

| Capability | Data available? | PRD/doc mandate? | Verdict |
|---|---|---|---|
| Search (`q`) | Yes: `title`, `sku` | Yes (API already grouped `%term%`, QRY-1) | Implement |
| Filter (`sni`, `tkdn`, `availability`) | Yes: `is_sni`, `tkdn_percentage`, `stock>0` | Yes (TKDN/SNI are B2G spec requirements, PRD §8) | Implement |
| Sort | Yes: `base_price`, `tkdn_percentage`, `created_at`, `id` | Yes | Implement (strict allowlist) |
| Category | **No model/table/column/seed** | **No** (PRD §8 requires TKDN/SNI, e-catalog status, price, specs — not category nav) | **Deferred by design** |

## URL contract (source of truth)

`GET /katalog` with optional query params:

| Param | Meaning | Normalization |
|---|---|---|
| `q` | search term (title **or** sku, case-insensitive) | trim, ≤120, strip control chars |
| `sni` | filter has-SNI | `1` = on; else off (no hard fail) |
| `tkdn_min` | min TKDN % | float, clamp 0–100; invalid → drop |
| `availability` | only in-stock (`stock>0`) | `1` = on |
| `sort` | `default`, `price_asc`, `price_desc`, `tkdn_desc`, `newest` | unknown → `default` |
| `page` | pagination | int ≥1; paginator clamps to lastPage |

**Locked parameter set (approved):** `q`, `sni`, `tkdn_min`, `availability`, `sort`, `page`.

**Sort allowlist mapping (never raw; final, no `title A-Z`):** `default`→`id asc`; `price_asc`→`base_price asc, id asc`; `price_desc`→`base_price desc, id asc`; `tkdn_desc`→`(tkdn_percentage IS NULL), tkdn_percentage desc, id asc` (portable SQLite+MySQL — known values, including 0, sort before NULL/unavailable; NULL always last); `newest`→`created_at desc, id asc`. Every sort carries an explicit `id asc` tie-break for deterministic pagination (UUID ids are time-ordered → insertion order).

**Page-reset:** a change to `q/sni/tkdn_min/availability/sort` resets `page`→1. **Back/Forward:** Inertia Link/router pushes each discovery state to history → native restore.

## Query / performance (MySQL 8.4 + SQLite tests)

- Fixed scope order; each filter = a bound WHERE on an existing column. Total = `COUNT` + `SELECT` (2 ops), no N+1.
- **No index migration now.** Table ~13 rows; filters low-selectivity; `%term%` not indexable (per repo policy); EXPLAIN evidence documentational only.
- **MIGRATION CANDIDATES (return only, do NOT implement):** (a) `created_at` index if `newest` sort at scale; (b) composite `(is_sni, stock)` if filter usage grows and EXPLAIN shows filesort on large data; (c) MySQL FULLTEXT `(title, sku)` only at thousands of rows. Each: {table, index, reason, expected query, verification = EXPLAIN on MySQL 8.4.3, rollback = drop index}.
- Build gate must EXPLAIN-verify final filtered+sorted shapes and confirm the default path matches baseline cost.

## API evolution (no change)

Public `GET /api/v1/products` and `ProductResource` are **untouched** by 3C.5. Discovery is the web `/katalog` path only. **Risk noted for a later phase (3C.8), not this one:** the *public* API index currently returns internal fields via `ProductResource` (`margin_percentage`, `tax_rate_percentage`, `estimated_shipping`, `stock`, `datasheet_url`) — recorded as an out-of-scope risk; must not be silently changed without an explicit API-evolution decision.

## Security & allowlists

- `sort`/columns/direction via strict enum allowlist only; no user text interpolated into SQL; all value params bound; order from enum table.
- `q` bounded length + control-strip; numeric clamp (`tkdn` 0–100, `page` ≥1); booleans accept only `1`; reject/replace array-flooded params (`$request->string()`); unknown dims ignored (no 500).
- Public payload remains exactly the 9 keys; `page` never leaks; `stock` never serialized.
- Public GET, read-only, no auth → no rate limit in 3C.5 (defer to 3C.8).

## Pagination interaction

Update `Pagination.vue`: `baseUrl` currently `/katalog` and `href = baseUrl?page=N` **drops discovery params**. Change to accept `baseUrl` as the discovery base (may contain `?`) and join page correctly (`&page=` when `?` present, else `?page=`). Server supplies the discovery query string from `normalizeDiscovery()` (page excluded). Preserve 44px targets + brand focus ring + `<span aria-disabled>` boundary pattern (GATE D fix must not regress).

## SEO / indexing strategy (SSR disabled)

- Clean `/katalog` → canonical `APP_PUBLIC_URL/katalog` (production) + indexable, H1 + existing og/meta (unchanged).
- **Any discovery-active state** (`?q`, `?sni`, `?tkdn_min`, `?availability`, `?sort≠default`, `?page>1`) → `<meta name="robots" content="noindex,follow">` + canonical back to clean `/katalog`. Prevents faceted-URL explosion; search-result pages have no indexing value (indexable value flows through 3C.6 product-detail URLs).
- No SSR; no server-rendered facet URLs.

## Frontend UX (discovery toolbar; procurement-native, anti-slop)

A single, calm **discovery toolbar** above the grid (not a mega filter sidebar, not pill fields, not a giant ecommerce search bar):

1. **Search** — compact labelled input "Cari produk (nama/SKU)" + submit; builds URL via Inertia `router.get` preserving other params; empty input drops `q`. Submit/Enter-based (no autocomplete/suggestions — no data justification).
2. **Filters** — small segmented/toggle controls: "Dengan SNI" (`aria-pressed`), "Tersedia", "TKDN ≥ 30/40/50" (numeric or segmented). Implemented as `<Link>`s → each state a real URL.
3. **Sort** — compact labelled `<select>` "Urutkan": Terbaru / Harga terendah / Harga tertinggi / TKDN tertinggi, with clear selected state.
4. **Active-filter summary + "Hapus filter"** reset link to clean `/katalog`.
- **EmptyState**: add discovery-aware copy variant ("Tidak ada produk yang cocok dengan filter") when `hasActiveDiscovery`, preserving the existing calm visual (copy-only change).
- Responsive: toolbar stacks on mobile; grid unchanged (`min-[520px]:2 / lg:3`).

## Accessibility

Labels on all inputs/selects; `aria-pressed` toggles; `aria-live`/`role="status"` on result-count + empty state; keyboard Enter submits search, Tab moves with `<span tabindex="-1" aria-disabled>` placeholders non-focusable; `prefers-reduced-motion` respected; focus-visible ring on all controls; contrast tokens unchanged.

## Tests & E2E strategy

**Unit:** `CatalogSort` mapping (incl. unknown→default, SQL-injection-token like `base_price),1);sleep(10)--` still resolves default, no error/injection). Normalization covered end-to-end via feature tests (no standalone filter class).
**Feature** (new `CatalogDiscoveryTest`, ~18–22):
- `?q=term` matches title AND sku, case-insensitive; composes with filters; no-match → empty + total 0.
- `?sni=1` only is_sni; `?tkdn_min=40` only ≥40; `?availability=1` only stock>0 with `available:true`.
- Each sort maps to expected first/last id; invalid sort → default; no 500 on garbage/tkdn_min=abc/page=0/arrays.
- Filtered response **still exactly 9 keys** (C5-style), no slug/stock leak.
- `hasActiveDiscovery` (noindex prop) true on discovery-active, false on clean; canonical present in prod env.
- Keep 20 baseline green.
**E2E (live MCP-browser):** type→Enter updates grid + URL `?q=`; toggle SNI → URL updates + page resets to 1; paginate with active filter → params retained; Back restores prior state; Tab ring visible; clear-filter resets; no dead-link regression on disabled pagination.

## Decisions & DO-NOT-IMPLEMENT

- **Locked params:** `q`, `sni`, `tkdn_min`, `availability`, `sort`, `page`.
- **Do NOT implement in 3C.5:** category (defer by design), API/ProductResource changes, SSR, autocomplete/suggestions, description-field search, mega filter sidebars, caching/CDN/Redis, any index migration (speculative), auth/registration, product-detail (3C.6), Cara Order (3C.7), security hardening incl. API field leak (3C.8).

## Sub-phase / gate structure (approved 3-gate layout — §AJ of GATE A review)

- **GATE A — Backend discovery core:** `CatalogSort` enum + `Product` scopes + `CatalogController::normalizeDiscovery()` + URL contract + security allowlists + pagination baseUrl + `hasActiveDiscovery`/noindex prop + full unit & feature matrix + MySQL EXPLAIN verification. Human review. (headless, except the existing `Catalog.vue` Head receiving the noindex prop)
- **GATE B — Frontend:** discovery toolbar (search/filter/sort), Pagination param-preserve, noindex/SEO Head, active-summary/clear, EmptyState copy variant, responsive sweep + a11y. Human review.
- **GATE C — Integration verdict:** full E2E browser, EXPLAIN/query-shape verification, security payload re-audit, SEO check, anti-slop/design pass, full SQLite + MySQL regression + Pint → verdict → checkpoint commit `feat(3C.5): ...`.

## Entry / exit criteria

- **Entry:** GATE D of 3C.4 clean; baseline suites green; HEAD `ec1f4d6` intact; no 3C.5 code yet (plan doc corrections only).
- **Exit:** all GATEs passed; 20 baseline + new discovery tests green on SQLite+MySQL; Pint clean; payload allowlist exact; anti-slop pass; checkpoint commit tagged `feat(3C.5)`; no push unless requested.

## Risk register

| Risk | Impact | Mitigation |
|---|---|---|
| Category wrongly implemented → speculative schema churn | High | Deferred by design (evidence: no data/PRD mandate); migration candidate only |
| Public API field leak (margin/tax/stock) changed silently | Med (later) | Flagged for 3C.8; out of scope; explicit API-evolution decision required before any change |
| Pagination drops discovery params | Med | Parameter-preserving baseUrl fix + E2E |
| `%term%` non-indexable at scale | Low now | Per repo policy acceptable; FULLTEXT candidate only at scale |
| Disabled-pagination dead links regress | Low | GATE D `<span aria-disabled>` pattern retained + E2E |
| Garbage query → 500 / SQL-injection | High | Strict allowlist + bound params + ignore-invalid normalization |

## Skills used (evidence-contributing)

Process: `brainstorming`, `writing-plans`. Laravel/engineering: `laravel:api-resources-and-pagination`, `laravel:api-surface-evolution`, `laravel:performance-*`, `laravel:routes-best-practices`, `laravel:constants-and-configuration`, `laravel:eloquent-relationships`, `laravel:complexity-guardrails`, `verification-before-completion`, `improve-codebase-architecture` (at build); design: `anti-ui-slop`, `web-design-guidelines`, `frontend-design-deslop`.

---

## Explicit decisions (persisted)

- **Category is DEFERRED BY DESIGN, not a missing implementation.** Not in 3C.5 scope.
- **No category schema, migration, model, relation, seed, or UI in Phase 3C.5.**
- **Existing `/api/v1/products` contract must not be silently broken or changed without an explicit API-evolution decision.**
- **§AO kickoff corrections applied:** (1) no standalone `ProductFilter` — private `normalizeDiscovery()` instead; (2) locked params `q/sni/tkdn_min/availability/sort/page`; (3) no `title A-Z` sort; (4) 3-gate structure; (5) `description` not searchable, no autocomplete.

## Status

**GATE A APPROVED 2026-09-20 — committed as `feat(3C.5): ...` (GATE A). No push; GATE B not started; awaiting GATE B authorization.**
