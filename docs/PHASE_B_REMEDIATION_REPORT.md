# Phase B Remediation Report — Foundation Hardening & Reliability

**Project:** Pengadaan Barang AJM (Laravel procurement platform)
**Phase:** B — Foundation Hardening & Reliability Remediation
**Date:** 2026-08-13
**Scope:** Approved P2/P3 findings from the Phase A audit (AUTH-2, SEC-2, AUD-1, SCHED-1, PERF-1, product search, pagination, product description/schema, factories, identifier collisions, migration rollback)

---

## 1. Findings Fixed

| ID | Severity | Finding | Fix |
|----|----------|---------|-----|
| AUTH-2 | High | No login rate limit; brute-forceable credentials | Native `RateLimiter::for('login')` in `AppServiceProvider::boot()` (5/min per email+IP, 429 JSON envelope in the API contract), token expiry already enforced via Sanctum's `expiration` config in earlier work; `AuthTest` now covers success / failure / throttle / per-email isolation / window reset |
| SEC-2 | High | `UserSeeder` hardcoded `password123`; no production-safe seeding path | Fail-closed seeder: `SEED_DEMO_USERS` must be `true` AND `SEED_ADMIN_PASSWORD` / `SEED_BUYER_B2B_PASSWORD` / `SEED_BUYER_B2G_PASSWORD` must be set in production, otherwise it throws `RuntimeException` with a message naming the missing variable; dev/test fall back to `password123` deterministically; `.env.example` documents the keys |
| AUD-1 | Medium | Audit gaps: login/logout, product CRUD, profile update, throttled login not audited | New `AuditAction` cases (`USER_LOGIN`, `USER_LOGOUT`, `LOGIN_FAILED`, `LOGIN_THROTTLED`, `PROFILE_UPDATED`, `PRODUCT_CREATED`, `PRODUCT_UPDATED`, `PRODUCT_DELETED`); wired into `AuthController`, `ProfileController`, `ProductController`; `audit_logs.entity_type/entity_id` made nullable (system/identity events have no entity); `AuditLogger::log()` accepts a nullable entity and snapshots previous/new state; throttle path logs `LOGIN_THROTTLED` via the limiter response callback. No passwords/tokens/headers ever logged |
| SCHED-1 | Medium | `CheckOverdueInvoices` ran one large transaction without row locks (contention risk at scale) | Query moved inside the transaction with `lockForUpdate()` on selected rows; schedule registration adds `->withoutOverlapping()`; idempotency regression test added (`Module8Test`) |
| PERF-1 | Medium | Missing indexes on `audit_logs` for the listing/order/filter query patterns | Migration `2026_08_13_091945_add_performance_indexes_to_audit_logs.php`: indexes on `created_at`, `(entity_type, action, created_at)`, `(action, created_at)`; EXPLAIN-verified on MySQL 8.4.3 (ref/backward index scan for the filter+order shapes) |
| QRY-1 | Medium | Product search used `%term` (prefix-only) and the `orWhere('sku')` was ungrouped (combined filters could misbehave) | Search now uses `%term%` on both title and sku inside an explicit `where(...)` closure so it composes with `is_sni`/`min_tkdn`/`in_stock`; regression tests added |
| VAL-1 | Medium | Unbounded `per_page` allowed a single massive listing query | `Controller::perPage()` helper clamps to 1..100 (default 15); applied at all 7 paginated listing controllers; clamp regression test added |
| P3 | Low | `products.description` was required at the DB level (option A in the refinement) while the request made it optional | Option B applied: `description` is nullable in both the request and the DB (migration `2026_08_13_110000_make_product_description_nullable.php`); `datasheet_url` already `500` — confirmed, no change needed |
| P3 | Low | `PaymentFactory::verified` didn't set `verified_by`; `ProductFactory::slug` used non-unique faker slugs | `PaymentFactory::verified()` now attaches a real `verified_by` user; `ProductFactory` uses `fake()->unique()->slug()` |
| P3 | Low | `Str::random(10)` business-number collisions (order_number, bast_number, invoice_number, rfq_number) could 500 a request | New `App\Services\UniqueIdentifier` retry-loop helper (checks the unique column, regenerates up to 10 attempts, throws a clear exception only on exhaustion); wired into OrderController (ORD-/BAST-), BastController (INV-), RfqController (RFQ-) |
| P3 | Low | Migration `down()` was broken for `2026_08_14_add_order_workflow_columns.php` (MySQL) and `2026_08_15_add_unique_index_on_orders_rfq_id.php` | Fixed both: (a) rfq_id unique index now drops its backing FK first, then restores it; (b) the order workflow `down()` widens the enum to the union, remaps newer statuses to legacy values, then narrows — verified with real `COMPLETED` data on MySQL 8.4.3 |

---

## 2. Implementation Notes

### AUTH-2 — login rate limiting (native)
- `AppServiceProvider::boot()` registers `RateLimiter::for('login', Limit::perMinute(5)->by($key))` where `$key = mb_strtolower((string) $request->input('email', '')).'|'.$request->ip()`. The per-IP fallback is removed from the original approach — the audit's requirement (per-email/IP) is satisfied by the composite key.
- The limiter's `->response()` callback returns the 429 JSON envelope (`{ success:false, message, data:null, errors:{throttle:[...]} }`) and logs `LOGIN_THROTTLED`.
- **Why not `bootstrap/app.php` middleware:** both `RateLimiter::for(...)` in `withMiddleware(...)` and the renderable-only approach failed at boot (`Class "cache.store" does not exist` — the middleware closure runs before the cache provider is bootstrapped). The provider `boot()` is the correct, stable location. Confirmed: the facade-root error is resolved and the full suite boots cleanly.

### SEC-2 — fail-closed seeding
- `config/app.php` gains a `demo` block: `seed_users` (from `SEED_DEMO_USERS`, default false), `admin_password`, `buyer_b2b_password`, `buyer_b2g_password`.
- `UserSeeder::resolvePassword($envName, $fallback)`:
  - Production → requires `config('app.demo.seed_users') === true` AND the named env password non-empty, else throws with a message naming the missing variable.
  - Non-production → deterministic `password123`.
- Tests simulate production via `$this->app->instance('env', 'production')` and call `(new UserSeeder)->run()` directly (bypasses the `db:seed` interactive confirmation).

### AUD-1 — audit events
- New enum cases (see findings table). `AuthController` logs `USER_LOGIN` on success, `LOGIN_FAILED` on failure (with the found user, or `null` entity for unknown emails), `USER_LOGOUT` on logout. `ProfileController` logs `PROFILE_UPDATED` with a previous-state snapshot. `ProductController` logs create/update/delete; `destroy` signature changed to `destroy(Request $request, $product)` so the audit logger (constructor-injected) can record the actor.
- `AuditLogger::log(?Model $entity)` is now null-safe; `STATE_FIELDS` covers `User` (full_name, email, company_name, role) and `Product` (sku, title, base_price, stock, is_sni). `is_active` was removed from the state map because neither model has that attribute (would throw `MissingAttributeException`).
- Migration `2026_08_13_091233_make_audit_log_entity_columns_nullable.php` makes `entity_type`/`entity_id` nullable.

### SCHED-1 — overdue command hardening
- `CheckOverdueInvoices::handle()` now wraps the selection and update in one transaction and locks the selected rows (`lockForUpdate()`). Verified idempotent: running it twice in a row affects 0 rows the second time and writes exactly one audit row per invoice.

### PERF-1 — audit_logs indexing (EXPLAIN evidence)
- New indexes (migration above). EXPLAIN on MySQL 8.4.3 against a 5,003-row table:
  - `action` filter → `ref` on `audit_logs_action_created_at_index`, **Backward index scan** (order satisfied by the index)
  - `entity_type + action` filter → `ref` on `audit_logs_entity_type_action_created_at_index`, **Backward index scan**
  - `entity_id` lookup → `ref` on the existing `(entity_type, entity_id)` index
  - Plain `latest()` on a tiny table still cost-model-selects a filesort (optimizer choice at <10k rows); the `created_at` index exists to serve it at scale
- No leading-wildcard LIKE is index-backed (product search uses `%term%`, which by design cannot use a B-tree prefix — acceptable for catalog search; do not add a B-tree index for it).

### Pagination clamp
- `Controller::perPage(?Request $request): int` clamps `per_page` to `[1, 100]`, default 15. Applied to: ProductController, OrderController, RfqController, InvoiceController, PaymentController, AuditLogController, NotificationController.

### Identifier retry loops
- `App\Services\UniqueIdentifier::generate(string $prefix, string $model, string $column): string` — loops up to `MAX_ATTEMPTS` (10) checking the target unique column, regenerating the token on each collision, and only then throws.

### Migration rollback (verified)
- `2026_08_15_add_unique_index_on_orders_rfq_id::down()`: `MySQL 1553 Cannot drop index ... needed in a foreign key constraint` → now drops the `rfq_id` FK, drops the unique index, then restores the FK.
- `2026_08_14_add_order_workflow_columns::down()`: `1265 Data truncated for column 'status'` when any order held `PENDING_PAYMENT`/`DELIVERED`/`COMPLETED` → now widens the enum to the union, maps `PENDING_PAYMENT→WAITING_PO`, `DELIVERED→SHIPPED`, `COMPLETED→PAID`, then narrows back to the legacy enum. Verified against real `COMPLETED` data.
- A full `migrate:fresh` → `migrate:rollback` cycle on MySQL 8.4.3 now completes cleanly with data present.

---

## 3. Verification Evidence (real command output)

### SQLite suite (default `phpunit.xml`)
```
vendor/bin/phpunit
146 tests / 144 passed / 2 skipped (pre-existing SQLite-only concurrency) / 856 assertions / ~38s
```
New tests added: `UserSeederTest` (4), `AuditLogTest` (7), `Module8Test` idempotency (1), `ProductTest` search/clamp (4).

### MySQL 8.4.3 suite (throwaway DB)
```
APP_ENV=testing DB_CONNECTION=mysql DB_DATABASE=ajm_phaseb_verify ...
php artisan test
146 tests / 146 passed / 860 assertions / ~46s  (0 skipped)
```

### Migration lifecycle (MySQL 8.4.3, throwaway DBs)
- `migrate:fresh` — all 22 migrations applied, including INT-7 `->change()`, the two new nullable migrations, and the new index migration.
- `migrate:rollback --step=7` and full `migrate:rollback` — all `down()` methods run cleanly **with data present** (COMPLETED order) after the two rollback fixes.

### EXPLAIN (PERF-1)
Captured above in §2 against a seeded 5,003-row `audit_logs` table on MySQL 8.4.3.

### Static & dependency gates
- `vendor/bin/pint --test` — clean (a handful of pre-existing style fixes applied by `pint`).
- `composer audit` — no security vulnerability advisories.
- `php artisan route:list` — all expected routes present.

---

## 4. Remaining Findings (explicitly deferred / out of scope)

| Item | Reason |
|---|---|
| INT-3 / INT-4 stock & overpayment caps | Scope refinement: stock is informational; strict rejection applies only at verification stage — **not** implemented in Phase B per the approved refinements |
| INT-6 lax invoice status transitions | Previously addressed in Phase A workflows; no further change |
| `OrderController::store` uses `QueryException` as control flow | Works and is covered by tests; considered acceptable for the RFQ-unique invariant |
| `PaymentRejectedNotification` | Not requested in the approved scope |
| `InvoiceResource::paid_amount` extra query per row | Performance improvement, low impact at this scale |
| Public `ProductResource` exposes pricing | Business decision, not a defect |
| Timestamps stored in UTC | Consistency item, deferred |
| `UserResource` PDP-relevant fields | Deliberate exposure for procurement identity; revisit in a privacy pass |

---

## 5. Documentation Changes

- `docs/PHASE_B_REMEDIATION_REPORT.md` — this report.
- `docs/CHANGELOG.md` — new top entry `[Phase B - Foundation Hardening & Reliability]`.
- `docs/MODULES_PROGRESS.md` — new **Phase B** section (status + gate).
- `docs/FOUNDATION_AUDIT_FIXES.md` — added Phase B deltas (nullable description, factory fixes, identifier retry loops).
- `docs/architecture.md`, `docs/database_schema.md` — updated to reflect the new migrations (audit_logs nullable entity + indexes, nullable product description).

---

## 6. Gate Decision

**PASS.**

Justification:
- All Phase B findings (AUTH-2, SEC-2, AUD-1, SCHED-1, PERF-1, QRY-1, VAL-1, and the P3 items) are implemented, tested, and verified with real command output.
- Suite is genuinely green on both engines: **146 / 144 / 2 skips (sqlite)** and **146 / 146 (MySQL 8.4.3, 0 skips)**.
- Migrations are reversible with data present (the two broken `down()` methods were reproduced, fixed, and re-verified).
- Static gates clean: `pint --test`, `composer audit`, `route:list`.
- Business behavior is preserved; no data-destroying operations; PHP 8.3 / Laravel 13 idioms respected throughout.
