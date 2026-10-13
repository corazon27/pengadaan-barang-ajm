# Project Changelog

## [Phase 3C.6 - Public Product Detail] - 2026-10-01
- **Product Detail page** â€” `GET /produk/{sku}` (route name `produk.show`) â†’ `App\Http\Controllers\ProductDetailController::show`. `/katalog` remains the public discovery (collection) surface; `/produk/{sku}` is the public detail (resource) surface.
- **Public identifier is the SKU** (not slug, not UUID). `ProductCard` became navigational: the whole card is one stretched Inertia `<Link>` to `/produk/${encodeURIComponent(sku)}`, with no nested interactive elements. The SKU was already in the 3C.5 catalog allowlist, so this required **no payload change, no new backend field, and no slug exposure**. The 3C.5 information hierarchy, TKDN/SNI markers and visual treatment are preserved.
- **Dedicated six-field public projection**, defined inline; ``ProductResource`` is **not** reused: `{ sku, title, description, price, warrantyInfo, available }``. `price` is a string, `available` a boolean. `stock` is selected only to derive `available` and is then dropped, so the raw count never reaches the client.
- **Security / public-data boundary:** excluded columns are never even loaded, so they cannot be serialized â€” raw `stock`, `margin_percentage`, `tax_rate_percentage`, `pph_rate_percentage`, `estimated_shipping`, `slug`, `datasheet_url`, `tkdn_percentage` / `is_sni`, `created_at` / ``update()`d_at`, NIB / NPWP / PKP, bank details, private supplier data, compliance data and transaction data.
- **Authorization is public and locked:** no auth middleware, no `ProductPolicy` invocation (it is not guest-safe), no new authorization framework. Not-found is the **plain Laravel 404** â€” no custom 404 system.
- **Case handling:** wrong-case SKU returns **404**, never a redirect. `products.sku` is `utf8mb4_unicode_ci` (case-insensitive) on MySQL 8.4.3, so a naive `where('sku', â€¦)` would wrongly return 200; enforcement is the portable PHP check `$product->sku !== $sku`. No `COLLATE`, no `LOWER()`, no functional index, no collation migration. A genuinely lowercase persisted SKU does resolve.
- **Canonical / SEO contract (six guarantees, no crawler-complete claim):** no `noindex` on Product Detail; exactly one canonical and one matching `og:url` in production; canonical derived from the **persisted SKU** via `route('produk.show', ['sku' => $product->sku], absolute: false)` (the parameter is explicit because implicit resolution would emit the UUID route key); non-production emits neither; and **F2 remains inherited / NOT REMEDIATED** â€” Blade-level canonical / `og:url` stay tied to the initial shell across client-side Inertia navigation under the locked SSR-disabled architecture. No SSR.
- **No TKDN/SNI expansion:** TKDN/SNI stay excluded from Product Detail while the 3C.5 catalog markers are untouched. Reintroduction requires evidence-based justification and legal/regulatory review. `ProductCertification` was **not** created.
- **Deferred:** media subsystem, publication state, `datasheet_url` exposure. **Out of scope:** API remediation (incl. the public API internal-field leak tracked as 3C.8), ``ProductResource``/API changes, migrations/schema, `ProductPolicy`, `app.blade.php`, Module 9, dashboards, transaction workflows, SSR, rate limiting, custom 404, shared `formatPrice` extraction, SKU write-path validation.
- Tests: new `tests/Feature/Web/ProductDetailTest.php` (29). **Full suite: SQLite `649 total / 636 passed / 13 skipped / 0 failed` (2877 assertions); MySQL 8.4.3 `649 total / 649 passed / 0 failed` (2914 assertions);** focused pre-regression `84 tests / 779 assertions`; focused Product Detail cross-engine subset on MySQL `12/12`; HTTP status matrix `13/13` with no redirects; `pint` âœ…; ``git diff --check`` âœ…; `npm run build` âœ… (563 modules; only the pre-existing optional `fontaine` warning). E2E: catalog â†’ detail â†’ back with discovery state preserved, keyboard-only navigation, skip link, mobile 375px with zero horizontal overflow, console clean. Desktop Product Detail + Catalog screenshots **approved by human visual review**.
- One test was removed during validation as invalid under the locked no-SSR architecture (`test_catalog_emits_the_product_detail_link` asserted server-rendered raw HTML for a link that only exists after Vue runtime rendering) and replaced by `test_catalog_payload_exposes_the_sku_needed_by_the_detail_link`; browser/E2E is the authoritative navigation verification layer.
- **COMMITTED as `205d1c8`; NOT PUSHED.** Master plan: `docs/superpowers/plans/2026-10-01-3c6-product-detail.md`; progress: `docs/MODULES_PROGRESS.md`.

## [Phase 3C.5 - Public Catalog Discovery (Search / Filter / Sort), GATE B - frontend discovery toolbar] - 2026-09-20
- **GATE B APPROVED by human visual review; committed as `bd7902c` (no push).**
- **Discovery toolbar** consolidated into a single self-contained **`DiscoveryToolbar.vue`** (search field + sort select kept internal, no separate SearchField/SortSelect files).
- **New `Discovery/DiscoveryToggle.vue`** â€” discovery filter toggles are real `<a>` links whose **accessible name is action-aware**: inactive `Filter SNI` / `Filter tersedia` / `Filter TKDN â‰¥ X%` â†’ active `Hapus filter SNI` / `Hapus filter tersedia` / `Hapus filter TKDN â‰¥ X%`. No `aria-current`, no `aria-pressed`, no `role="button"`, no `aria-pressed`; keyboard activation + Back/Forward via native anchor behavior only.
- **New `Discovery/ActiveFilters.vue`** â€” active-filters register ("Filter aktif" list) + "Hapus filter" reset link, non-live (register never announces).
- **Single live region:** exactly one `role="status"` result-status line ("Menampilkan N produk â€¦") per state; active-filter row is non-live; empty state keeps its own `role="status"` (still one region â€” replaces the result line when the grid is absent).
- **Pagination fix (`Pagination.vue`):** `&page=` join now correct when the discovery base already contains `?`; page always resets to 1 on any discovery change (search/sni/tkdn/availability/sort), and server-side `CatalogController` normalizes the echoed discovery prop (no client-side normalization).
- **Empty state (discovery-aware):** `EmptyState.vue` + `Catalog.vue` show "Tidak ada produk yang cocok dengan filter"/empty-state discovery copy + "Lihat semua produk" reset action when a discovery is active.
- Tests: `tests/Feature/Web/CatalogDiscoveryTest.php` (D1 discovery prop assertions) + `tests/Unit/CatalogSortTest.php`. **Full suite: SQLite `617 total / 604 passed / 13 skipped / 0 failed`; MySQL 8.4 `617 / 617 / 0 / 0`; Pint clean; `npm run build` âœ… (no Vite errors); E2E browser pass (toggles/URL/page-reset/`&page=` join/live region/aria-names) + screenshots (`e2e/*.png`) approved visually by a human.**

## [Phase 3C.5 - Public Catalog Discovery (Search / Filter / Sort), GATE A] - 2026-09-20
- **Backend discovery core for the web `/katalog` path** â€” search (`?q=` title OR sku, case-insensitive), filter (`?sni=1`, `?tkdn_min=<0-100>`, `?availability=1`), and sort (`default|price_asc|price_desc|tkdn_desc|newest`) driven entirely by the URL, with browser Back/Forward supported and `page` reset to 1 on discovery change.
- New `App\Enums\CatalogSort` â€” strict, allowlisted ordering (each non-default sort carries an explicit `id` tie-break for deterministic pagination); unknown/injection-token values resolve to `default` (never interpolated into SQL).
- `App\Models\Product` gains allowlisted scopes `search` (grouped `%term%` on title+sku only; description intentionally not searchable), `sni`, `tkdnMin`, `available` (`stock > 0`).
- `CatalogController` now normalizes the query string (`q` bounded 120 chars + control-char strip; `tkdn_min` clamped 0â€“100; array-flooded params ignored; garbage never 500s), applies scopes + enum ordering, and exposes `pagination.baseUrl` (discovery params preserved, `page` excluded) plus `hasActiveDiscovery` (any of q/sni/tkdn_min/availability/sortâ‰ default or `page>1`).
- Payload allowlist remains **exactly** `{id, sku, title, description, price, tkdn, hasSni, warrantyInfo, available}`; raw `stock` is never serialized. No change to `/api/v1/products` or ``ProductResource``. No migration, no category, no SSR.
- **SEO delivery:** discovery-active states emit `<meta name="robots" content="noindex,follow">` in the initial HTML; production (`APP_PUBLIC_URL` + `env=production`) emits canonical + `og:url` pointing to clean `/katalog`, exactly once each, server-rendered in `resources/views/app.blade.php` (SSR disabled). Clean `/katalog` stays indexable.
- Tests: new `tests/Unit/CatalogSortTest.php` (8) + `tests/Feature/Web/CatalogDiscoveryTest.php` (~35, incl. initial-HTML SEO delivery).
- Verified: SQLite suite âœ… **613 tests / 600 passed / 13 skipped / 2637 assertions**; MySQL 8.4 suite âœ… **613 / 613 / 2674 assertions / 0 skipped**; `pint` âœ…; ``git diff --check`` âœ…; raw initial-HTML SEO contract verified.
- **Gate decision: GATE A APPROVED.** GATE B (frontend discovery toolbar) authorized separately. Master plan: `docs/superpowers/plans/2026-09-01-3c5-catalog-discovery.md`.

## [Phase B - Foundation Hardening & Reliability Remediation] - 2026-08-13
- **AUTH-2 (High):** native login rate limiting via `RateLimiter::for('login')` in `AppServiceProvider::boot()` â€” 5 attempts/min per email+IP with a 429 JSON envelope and `LOGIN_THROTTLED` audit row. (Not in `bootstrap/app.php`: the middleware closure runs before the cache provider, so it fatals.)
- **SEC-2 (High):** `UserSeeder` is now fail-closed in production â€” requires `SEED_DEMO_USERS=true` and the `SEED_ADMIN_PASSWORD`/`SEED_BUYER_B2B_PASSWORD`/`SEED_BUYER_B2G_PASSWORD` env vars, else throws; dev/test stay deterministic. Config via new `app.demo` block; `.env.example` `update()`d; `UserSeederTest` (4 tests) added.
- **AUD-1 (Medium):** audit coverage extended â€” `USER_LOGIN`, `USER_LOGOUT`, `LOGIN_FAILED`, `LOGIN_THROTTLED`, `PROFILE_`update()`D`, `PRODUCT_CREATED`/``update()`D`/`DELETED`; `audit_logs.entity_type/entity_id` nullable (migration `2026_08_13_091233_make_audit_log_entity_columns_nullable.php`); `AuditLogger::log()` accepts nullable entities; `AuditLogTest` (7 tests) added.
- **SCHED-1 (Medium):** `CheckOverdueInvoices` now locks selected rows inside the transaction and runs with `->withoutOverlapping()`; idempotency regression test added.
- **PERF-1 (Medium):** new `audit_logs` indexes (`created_at`, `(entity_type, action, created_at)`, `(action, created_at)`) â€” migration `2026_08_13_091945_add_performance_indexes_to_audit_logs.php`, EXPLAIN-verified on MySQL 8.4.3.
- **QRY-1 / VAL-1 (Medium):** product search now `%term%` and properly grouped inside a `where(...)` closure; pagination clamped to 1..100 (default 15) via `Controller::perPage()` at all 7 listing controllers.
- **P3:** `products.description` nullable at DB level (Option B, migration `2026_08_13_110000_make_product_description_nullable.php`); `PaymentFactory::verified()` sets `verified_by`; `ProductFactory` uses unique slugs; new `UniqueIdentifier` retry-loop service for ORD-/BAST-/INV-/RFQ- numbers.
- **P3 (rollback fixes):** `2026_08_15_add_unique_index_on_orders_rfq_id::down()` drops the backing FK before the unique index; `2026_08_14_add_order_workflow_columns::down()` widens/remaps/narrows the status enum â€” both reproduced with data on MySQL 8.4.3 and fixed.
- Verified: SQLite suite âœ… **146 / 144 passed / 2 pre-existing skips / 856 assertions**; MySQL 8.4.3 suite âœ… **146 / 146 / 0 skipped / 860 assertions**; `migrate:fresh` + full `migrate:rollback` âœ… on MySQL with data; `pint` âœ…; `composer audit` âœ…; `route:list` âœ….
- **Gate decision: PASS.** Full report in `docs/PHASE_B_REMEDIATION_REPORT.md`.

## [Phase A - Audit Remediation: Security, Data Integrity & Test Discovery] - 2026-08-15
- **TEST-1 (High):** rewrote ``	ests/Feature/Api/ProductTest.php`` with `test_` prefixes â€” PHPUnit 12 dropped `/** @test */`, so the class (9 tests) was silently excluded and the earlier "green" suite was a false green. Discovered tests now run.
- **AUTH-1 (High):** `ProductController::`destroy()`` now enforces `ProductPolicy@delete`; non-superadmin `DELETE /products/{product}` â†’ 403 (was 200). Added `test_non_admin_receives_403_on_product_delete`.
- **SEC-1 (Medium):** payment proofs now `store()`d on the private `documents` disk (`payments/proofs/...`) with `proof_file_url` holding the private path (no public URL); added guarded `GET /api/v1/payments/{payment}/proof` streaming endpoint (200/401/403/404). `PaymentTest` 14 â†’ 20 tests.
- **INT-1 (Medium):** migration `2026_08_15_add_unique_invoice_per_order.php` (unique `invoices_order_unique` on `order_id`); `BastController::sign` now runs inside a transaction with `lockFor`update()`()` on the order row and audits the locked order. Re-sign â†’ 422, exactly one invoice guaranteed. `InvoiceTest` 8 â†’ 13 tests.
- **INT-2 (Medium):** migration `2026_08_15_relax_orders_top_days_check.php` relaxes the `orders.top_days` CHECK from `> 0` to `>= 0` (MySQL/PostgreSQL) so `IMMEDIATE` (0-day) terms persist; `IMMEDIATE` invoice `due_date = issued_date`. `Module8Test` analytics data restructured for the one-invoice-per-order invariant.
- **INT-3 (High, discovered during MySQL verification):** the `orders.rfq_id` UNIQUE index declared in the create migration was never emitted by Laravel (chained `constrained()` + `unique()`), leaving the one-order-per-RFQ invariant unenforced at the DB level. Added idempotent migration `2026_08_15_add_unique_index_on_orders_rfq_id.php` (both engines); MySQL-only direct-insert concurrency tests now pass.
- Rewrote `OrderTest::test_orders_table_has_unique_index_on_rfq_id` on `Schema::getIndexes()` (Laravel 13 removed `getDoctrineSchemaManager`); now runs on sqlite too. Fixed overdue-invoice test data to keep `due_date >= issued_date` (MySQL `invoices_due_after_issued`).
- Verified: SQLite suite `vendor/bin/phpunit` âœ… **118 tests / 116 passed / 2 skipped / 690 assertions**; MySQL 8.4.3 suite âœ… **118 / 118 / 694 assertions / 0 skipped**; `migrate:fresh --seed` âœ… MySQL; `pint --test` âœ…; `composer audit` âœ…; `route:list` âœ….
- **Gate decision: PASS.** Full report in `docs/PHASE_A_REMEDIATION_REPORT.md`.

## [Module 8 - Audit Trail, Overdue Invoice Scheduler & Executive Analytics API] - 2026-08-13
- Added failure-tolerant `AuditLogger` service + `AuditAction` enum logging creations and status transitions for RFQ / Order / BAST / Invoice / Payment (previous & new state snapshots).
- Added `GET /api/v1/audit-logs` (Superadmin-only, filters `entity_type` + `action`, paginated) with `AuditLogResource` + `AuditLogPolicy`.
- Added `invoices:check-overdue` command marking past-due `UNPAID`/`PARTIALLY_PAID` invoices `OVERDUE` in one transaction with one audit row each; registered to run daily via `withSchedule()` in `bootstrap/app.php`.
- Added `GET /api/v1/analytics/dashboard` (Superadmin-only) reporting RFQs by status, Orders by status + value, outstanding receivables, verified payments, and quantity-weighted average TKDN.
- Registered `AnalyticsPolicy` explicitly in `AppServiceProvider` (no backing model for auto-discovery).
- Added `tests/Feature/Api/Module8Test.php` (14 tests) covering audit generation, listing/filtering, 401/403, the overdue command, and analytics metrics.

## [Phase 1 - Inisiasi Fondasi] - 2026-08-12
- Configured `.env` for local MySQL (Laragon).
- Created database `pengadaan_barang_ajm`.
- Created PHP 8.3 Enums: `UserRole`, `RfqStatus`, `OrderStatus`, `InvoiceStatus`.
- Fixed PSR-4 autoload namespace structure for Enums under `app/Enums`.
- Created and executed MySQL database migrations for all tables:
  - `users` (procurement roles & company profiles)
  - `products` (TKDN, SNI, tax rates, margins)
  - `rfqs` & `rfq_items`
  - `orders` & `order_items`
  - `bast_documents`
  - `invoices`
  - `audit_logs`
- Created Eloquent Models with `HasUuids` trait, relationships, Enum casts, and strict type definitions.
- Implemented `UserFactory` and `ProductFactory`.
- Implemented `UserSeeder` (seeded Superadmin, Buyer B2B, and Buyer B2G accounts).
- Implemented `ProductSeeder` (seeded B2B/B2G TKDN & SNI procurement products).
- Successfully executed `php artisan migrate:fresh --seed`.

## [Phase 2 - Audit Remediation: Foundation & Integrity] - 2026-08-12
- Consolidated `users` schema into the base migration and deleted the data-`destroy()`ing ``update()`_users_table_for_procurement` migration.
- Enforced enums at DB level (`users.role`, `rfqs.status`, `orders.status`, `invoices.status`) and added 8 driver-guarded CHECK constraints.
- Hardened FKs/columns: composite FK on `invoices(bast_id, order_id)`, unique `orders.rfq_id`, `unsigned` money/quantity/percent, `uuid()` PKs, correct delete rules.
- Removed sensitive/computed fields from `$fillable` (User.role, Rfq.status, Order.total_amount, OrderItem.unit_price/subtotal).
- Server-side price/total computation in `Order`/`OrderItem` hooks (BC Math); model `creating` defaults for enum statuses.
- Enabled strict Eloquent guards (`preventLazyLoading`, `preventSilentlyDiscardingAttributes`, `preventAccessingMissingAttributes`) in non-production.
- Added `declare(strict_types=1)` to controllers, models, seeders, and factories.
- Added 7 factories, idempotent `ProductSeeder`, and `tests/Feature/ModelIntegrityTest` (7 tests / 15 assertions).
- Added test-only `APP_KEY` to `phpunit.xml`.
- Verified: `migrate:fresh --seed` âœ… (MySQL 8.4.3), `php artisan test` âœ… 7/7, `pint --test` âœ….
- Full context in `docs/FOUNDATION_AUDIT_FIXES.md`.

## [Module 1 - API Authentication & User Profile] - 2026-08-12
- Installed `laravel/sanctum` via `php artisan install:api` (v4.3.3) and registered `api:` routing with `apiPrefix('api/v1')` in `bootstrap/app.php`.
- Adapted the Sanctum `personal_access_tokens` migration to `uuidMorphs('tokenable')` to match the UUID primary keys of the `users` table.
- Added `Laravel\Sanctum\HasApiTokens` to the `User` model; login issues a Sanctum token carrying role claims (`role:SUPERADMIN`, `role:BUYER_B2B`, `role:BUYER_B2G`) as token abilities.
- Added uniform API response envelope `{ success, message, data, errors }` via `App\Traits\ApiResponser`; shaped global `ValidationException` (422) and `AuthenticationException` (401) into the same envelope in `bootstrap/app.php`.
- Added auth endpoints: `POST /api/v1/auth/login` (public), `POST /api/v1/auth/logout`, `GET /api/v1/auth/me`, `PUT /api/v1/auth/profile` (all `auth:sanctum`).
- Added `LoginRequest` / ``update()`ProfileRequest` (`app/Http/Requests/Auth`), `UserResource`, `AuthController`, `ProfileController`, and `UserPolicy` (self or superadmin) with automatic policy discovery.
- Added `tests/Feature/Api/AuthTest` (8 tests: login success/token abilities, invalid credentials, validation, `me`, profile `update()`, role/email immutability, logout revocation, unauthenticated access).
- Verified: `pint --test` âœ…, `php artisan test` âœ… 15/15 (66 assertions), live smoke test on MySQL âœ… (login/me/`update()`/logout/revoked-token â†’ 401).

## [Module 2 - Product Catalog API] - 2026-08-13
- Introduced `Product` model with UUID primary key and extensive attribute set.
- Created `ProductFactory` with superadmin role capability for seeding.
- Developed ``store()`ProductRequest` and ``update()`ProductRequest` with comprehensive validation, including uniqueness checks for `sku` and `slug`.
- Implemented ``ProductResource`` for clean API output serialization.
- Added `ProductPolicy` enforcing public read access and superadminâ€‘only CRUD permissions.
- Built `ProductController` with:
  - `index` (public search/filter pagination),
  - `show` (public detail view),
  - ``store()``/``update()``/``destroy()`` (protected by `auth:sanctum` and `ProductPolicy`).
- Added routes under `/api/v1/products` (public list/show, protected CRUD).
- Wrote `ProductTest` covering public filtering, private CRUD, and roleâ€‘based access control.
- Verified live MySQL flow: product creation, `update()`, delete by superadmin, and 403 enforcement for nonâ€‘admin.

## [Module 3 - RFQ Workflow API] - 2026-08-13
- Extended `RfqStatus` enum with `QUOTED` and `CANCELLED`; added `valid_until`/`admin_notes` to `rfqs` and `target_price`/`notes` to `rfq_items` via migration.
- Added `RfqItemResource` and `RfqResource` with computed totals and nested product details.
- Created ``store()`RfqRequest` (items with product_id, quantity, target_price), `RespondRfqRequest` (Superadmin: offered_price, valid_until), ``update()`RfqStatusRequest`.
- Implemented `RfqPolicy`: public read isolation (buyers see own RFQs), Superadmin full access, owner status transitions (SUBMITTED/QUOTED â†’ CANCELLED; QUOTED â†’ APPROVED/REJECTED/CANCELLED), Superadmin-only quotation response.
- Built `RfqController` with transactional ``store()`` (RFQ + items), `respond` (`update()`s `negotiated_price`, sets `QUOTED`, `valid_until`), ``update()`Status` (validated transitions).
- Added routes under `/api/v1/rfqs`: `GET` (list), `POST` (create), `GET {rfq}` (show), `POST {rfq}/respond` (Superadmin), `PATCH {rfq}/status` (owner/Superadmin).
- Added `RfqTest` (12 tests): buyer CRUD isolation, Superadmin list/respond, owner accept/reject/cancel, Superadmin-only respond, invalid transition 422, 403 enforcement.
- Verified: `pint --test` âœ…, `php artisan test` âœ… 27/27 (123 assertions).

## [Module 4 - Order & Invoicing Workflow API] - 2026-08-14
- Replaced `OrderStatus` enum with the lifecycle set `PENDING_PAYMENT`, `PROCESSING`, `SHIPPED`, `DELIVERED`, `COMPLETED`, `CANCELLED`; created `BastStatus` enum (`PENDING_SIGNATURE`, `SIGNED`).
- Added `database/migrations/2026_08_14_add_order_workflow_columns.php`: MySQL-guarded ALTER of `orders.status` enum; `bast_documents` gains `status`, `signed_by`, `signed_at`, `notes` and nullable `bast_document_url`/`signed_date`; `invoices` gains `subtotal`, `tax_amount`, `grand_total`.
- Extended `Order`, `BastDocument`, and `Invoice` models with the new fields/casts and `statusLabel()` helpers.
- Added `OrderController` (list/show, RFQâ†’order conversion, status transitions), `BastController` (show/sign), `InvoiceController` (list/show/payment-status).
- Conversion (`POST /orders`) requires an `APPROVED` unconverted RFQ; items are copied at the quoted `negotiated_price`; RFQ moves to `CONVERTED_TO_ORDER`.
- Status state machine: forward moves Superadmin-only, cancellation allowed for owner/Superadmin; invalid transitions â†’ 422. `DELIVERED` auto-generates a `PENDING_SIGNATURE` BAST; signing the BAST completes the order and auto-generates an UNPAID invoice (subtotal/tax/grand_total, due in `top_days`).
- Added `OrderPolicy`, `BastDocumentPolicy`, `InvoicePolicy`; `InvoiceResource` exposes `payment_status`; Superadmin-only invoice payment-status `update()`s (sets `paid_at` on PAID).
- Registered order/BAST/invoice routes under `/api/v1`.
- Added `OrderTest` (10 tests) and `InvoiceTest` (8 tests) covering conversion rules, role isolation, status transitions, BAST generation/signing, invoice generation, and payment-status RBAC.

## [Audit - Security, Concurrency & Precision Fixes] - 2026-08-14
- **TOCTOU race condition fixed** in RFQâ†’Order conversion (`OrderController::`store()``): `lockFor`update()`()` on the RFQ row inside `DB::transaction()` plus an in-transaction duplicate check; `QueryException` unique-violation is caught and returned as 422.
- **RFQ item ownership enforced** (`RfqController::respond`): a submitted `rfq_item_id` belonging to a different RFQ is now rejected with HTTP 422 (`items.*.rfq_item_id` error) instead of being silently ignored; no partial writes occur.
- **`OrderPolicy::create`** now accepts the target `Rfq` and verifies ownership (`user->is($rfq->user)`) or Superadmin; controller passes the RFQ instance.
- **BC Math precision** confirmed/enforced for all monetary computation in `BastController::generateInvoice()` (per-item tax = subtotal Ã— rate Ã· 100, summed, then grand total; 2-decimal scale).
- **Tests added**: duplicate RFQâ†’order conversion rejected (422), DB unique constraint on `orders.rfq_id` verified via direct insert (MySQL), foreign `rfq_item_id` rejection with no partial writes, and valid-item regression test. Concurrency/direct-insert tests skip on SQLite (in-memory DB does not reliably enforce unique constraints).
- Verified: `pint` âœ…, `php artisan test` âœ… 49 passed / 3 skipped (SQLite-only skips), 239 assertions.

## [Module 5 - Payment Processing & Payment Terms (TOP / Tax Handling) API] - 2026-08-14
- Added `PaymentTerm` enum (`IMMEDIATE`, `TOP_14`, `TOP_30`, `TOP_60`) with day counts; `invoices.payment_term` snapshot + auto `due_date = issued_date + term.days()` at issuance.
- Renamed `invoices.tax_amount` â†’ `ppn_amount`; added `invoices.pph_amount`, `invoices.payment_term`, and nullable `products.pph_rate_percentage` via migration `2026_08_14_add_payment_processing.php`; created `payments` table.
- Tax breakdown exposed in `InvoiceResource`: `subtotal`, `ppn_amount`, `pph_amount`, `grand_total`, `payment_term`; per-item PPN/PPh via BC Math; **grand_total = subtotal + PPN** (PPh is a withholding, recorded separately).
- Created `payments` ledger: buyer submits proof (`amount`, `payment_method`, `payment_date`, `proof_file` â‰¤5MB image/PDF, `notes`) â†’ `PENDING_VERIFICATION`; Superadmin lists (status filter) and verifies/rejects.
- Reconciliation on verify (transaction + `lockFor`update()``): Î£ VERIFIED â‰¥ `grand_total` â†’ `PAID` + `paid_at`; partial â†’ `PARTIALLY_PAID`; none â†’ `UNPAID`. `InvoiceStatus` gains `PARTIALLY_PAID`.
- Added `SubmitPaymentRequest`, `VerifyPaymentRequest` (rejection reason required when rejecting), `PaymentResource`, `PaymentPolicy` (owner submit, Superadmin verify), `PaymentController` (``store()``/`index`/`verify`).
- Registered routes: `POST /invoices/{invoice}/payments`, `GET /payments`, `PATCH /payments/{payment}/verify`.
- Added `PaymentTest` (14 tests): upload validation, owner isolation (403), full/partial/multi-payment reconciliation to `PAID`/`PARTIALLY_PAID`, rejection with reason, already-settled 422, RBAC, listing scoping, resource breakdown; `InvoiceTest` `update()`d for the rename + PPh test.
- Verified: `pint` âœ…, `php artisan test` âœ… 64 passed / 3 skipped (SQLite-only skips), 384 assertions.

## [Module 6 - Notification System (In-App & Email) API] - 2026-08-14
- Added `notifications` table (UUID PK via `uuidMorphs`, `type`, text `data`, nullable `read_at`) via migration `2026_08_14_create_notifications_table.php`; `App\Models\Notification` extends `DatabaseNotification` + `HasUuids` with an `isRead()` helper.
- Added 4 queued notification classes (`ShouldQueue`, `database` + `mail` channels, Indonesian `MailMessage`): `RfqSubmittedNotification` (to all SUPERADMINs), `RfqRespondedNotification` (to RFQ owner), `OrderShippedNotification` (to buyer on `SHIPPED`), `PaymentVerifiedNotification` (to buyer on verification with reconciled `PAID`/`PARTIALLY_PAID` status). All triggers fire after the enclosing `DB::transaction` commits.
- Added `NotificationResource` (`id`, `type` basename, `title`, `message`, `action_url`, `read_at`, `is_read`, `created_at`) and `NotificationController` (`index` with `data.items` + `data.pagination`, `markAsRead`, `readAll`); `NotificationPolicy` limits view to SUPERADMIN/recipient and read-marking to the recipient.
- Registered routes: `GET /notifications`, `PATCH /notifications/{notification}/read`, `POST /notifications/read-all` (all `auth:sanctum`).
- Wired dispatch triggers in `RfqController` (``store()``, `respond`), `OrderController` (``update()`Status` on `SHIPPED`), and `PaymentController` (`verify` on `VERIFIED`).
- Added `NotificationTest` (8 tests: newest-first list + read indicator, pagination meta, mark-one-read, read-all, 403 cross-user, 401 unauthenticated, real database-channel dispatch, per-buyer isolation); extended `RfqTest`/`OrderTest`/`PaymentTest` with `Notification::fake()` + `assertSentTo`.
- Verified: `pint` âœ…, `php artisan test` âœ… 72 passed / 3 skipped (SQLite-only skips), 453 assertions.
## [Module 7 - Document Engine (PDF Generation)] - 2026-08-13
- Installed `barryvdh/laravel-dompdf` (v3.1.2, pure PHP) for PDF rendering; added a private local `documents` disk (`storage/app/private/documents`) to `config/filesystems.php`.
- Added `config/company.php` letterhead config (name, legal_entity, nib, pkp, npwp, address, phone, email, website, bank details), all env-driven.
- Added `app/Services/PdfService.php` (`generate()` renders a Blade view, `store()`s the PDF on the documents disk, returns the relative path or an empty string + `report()` on failure; `sanitizeFilename()` for safe download filenames).
- Added `app/Http/Controllers/Api/Document/DocumentController.php` with streaming download endpoints: `GET /rfqs/{rfq}/quotation.pdf`, `GET /orders/{order}/bast.pdf`, `GET /invoices/{invoice}/pdf` - `application/pdf` inline responses, 404 `Dokumen belum tersedia.` when the file is missing, authorized via `RfqPolicy`/`BastDocumentPolicy`/`InvoicePolicy`.
- Created Blade templates `resources/views/pdf/{partials/styles, partials/kop-surat, rfq-quotation, bast, invoice}.blade.php` - A4 dompdf-compatible layout, letterhead header, item tables, tax summary, signature blocks, bank transfer details, Indonesian dates (`translatedFormat('j F Y')`) and `1.234.567,89` money formatting.
- Wired generation hooks (run after the success transaction; PDF failure is logged and the business action still succeeds): quotation PDF on `RfqController::respond` (`rfqs.quotation_pdf_url`); BAST draft PDF when the order transitions to `SHIPPED` (`bast_documents.bast_document_url`); invoice PDF on BAST sign (`invoices.invoice_pdf_url`).
- Moved `BastDocument` creation from the `DELIVERED` to the `SHIPPED` branch of `OrderController::`update()`Status` (state machine `PROCESSING -> SHIPPED -> BAST_SIGNED`); removed the placeholder URL assignments in `BastController`.
- Added `DocumentTest` (10 tests): quotation/BAST/invoice PDF generation + storage (`%PDF` header check on the documents disk), owner download 200 + `application/pdf`, 404 when missing/URL empty, 401 unauthenticated, 403 cross-user, and `config('company')` completeness.
- Verified: `pint` OK, `php artisan test` OK: 82 passed / 3 skipped (SQLite-only skips) / 506 assertions.## [2026-10-03] - PHASE 3C.7 â€” Cara Order (Informational Page)

### Added
- Public informational page
esources/js/Pages/CaraOrder.vue at route GET /cara-order (named cara-order.show). Static content covering B2B/B2G procurement context with conservative boundary wording; no public RFQ/transaction functionality.
- Feature tests tests/Feature/Web/CaraOrderTest.php (3 tests, 9 assertions).

### Changed
- Header navigation: 'Cara Order' becomes real link to /cara-order (
esources/js/Components/Header.vue). 'Produk' and 'Kontak' unchanged.

### Validation
- Full regression: 652 passed, 13 skipped, 0 failed (2886 assertions). ProductDetail 29/29, CaraOrder 3/3.
- Pint PASS;
pm run build PASS.
- Human visual review (desktop/mobile) APPROVED. Implementation and documentation CLOSED; COMMITTED â€” NOT PUSHED.

## [Phase 3C.8 - API/Public Data Exposure & Security Surface] - 2026-10-10
- **Public Product API contract enforced:** introduced pp/Http/Resources/Product/PublicProductResource with exact seven-field projection { id, sku, title, description, price, warranty_info, available }. price is string derived from ase_price; vailable is boolean from stock > 0.
- **Controller isolation:** ProductController@index and ProductController@show use the public resource for public reads; store() and update() retain ProductResource; destroy() unchanged; existing authorization preserved.
- **Security tests:** added HTTP-level assertions in 	ests/Feature/Api/ProductTest.php enforcing allowlist and forbidding { slug, margin_percentage, tax_rate_percentage, estimated_shipping, datasheet_url, stock, tkdn_percentage, is_sni, base_price } on actual response payloads (including ID/slug lookups).
- **Verification:** API tests 12/12 (77 assertions); web regression (ProductDetail/CatalogDiscovery/CaraOrder) 74/74 (636 assertions); full suite 652 tests (639 passed, 13 skipped, 0 failed), 2919 assertions; Pint and PHP syntax checks pass; git diff --check clean.

## [Public Surface Rate-Limiting Follow-up] - 2026-10-12
- **Named limiters `public-web` and `public-api`** added in `AppServiceProvider`: 60 requests/minute keyed by `$request->ip()`, with separate web and API buckets. The 60/minute threshold is a provisional engineering setting, not a measured traffic limit, and no forwarded header is trusted.
- **Protected GET endpoints:** `throttle:public-web` on `GET /katalog` and `GET /produk/{sku}`; `throttle:public-api` on `GET /api/v1/products` and `GET /api/v1/products/{product}`. `/`, `/cara-order`, `POST /auth/login` (existing `login` limiter, unchanged) and all authenticated and mutation routes are unaffected; the 3C.8 seven-field public Product API contract is intact and no trusted-proxy configuration was introduced.
- **429 behavior preserved:** the existing public API JSON error envelope is unchanged, and the renderer now forwards the framework throttle headers (`Retry-After`, `X-RateLimit-*`).
- **Verification (local):** focused rate-limit tests 12 passed / 692 assertions; regression 114 passed / 966 assertions; SQLite full suite 664 total / 651 passed / 13 skipped / 0 failed; MySQL 8.4.3 full suite 664 / 664 / 0 skipped / 0 failed / 3,648 assertions. Pint, PHP syntax checks and `git diff --check` pass.
- **Committed locally as `e03d7ee` (NOT PUSHED).** Production activation has NOT occurred: whether Rumahweb Shared Hosting Small presents the original visitor IP in `REMOTE_ADDR` (and whether trusted proxies are required) is unconfirmed, and a safe production route-cache refresh mechanism still needs verification.

## [Account Access & Approval Lifecycle] - 2026-10-13
- **Scope delivered (BUILD MODE, local only):** self-service registration, guest email verification, account lifecycle PENDING/ACTIVE/REJECTED/SUSPENDED, Inertia session authentication alongside the unchanged Sanctum API, SUPERADMIN-only account review, B2G evidence submission/review, notifications, audit, a CLI provisioning command, and the corresponding tests and UI pages. No buyer/admin analytics dashboards; no ACL/capability framework; roles unchanged (SUPERADMIN, BUYER_B2B, BUYER_B2G).
- **Runtime authorization is `status` only:** `email_verified_at` is a prerequisite for administrator approval and never an authorization gate; legacy ACTIVE accounts may keep a NULL `email_verified_at` (no fabricated timestamps). `requested_role` records intent only and grants nothing; registration always creates `BUYER_B2B`, and `BUYER_B2G` is granted only through approval after an ACCEPTED evidence submission. Active B2B->B2G upgrade is out of scope for this release.
- **Schema:** `2026_10_13_000001_add_account_lifecycle_to_users_table` adds `status`, `status_reason`, `status_changed_at`, `requested_role` with a NULL-sentinel grandfathering backfill (legacy NULL -> ACTIVE, then NOT NULL default PENDING) and a `status` index; `2026_10_13_000002_create_b2g_evidence_submissions_table` is an append-only versioned evidence table with `unique(user_id, version)` and a `(user_id, status)` index. `status` is deliberately not mass-assignable (set via a `creating` hook default and `forceFill`).
- **Lifecycle + strict audit:** `AccountLifecycleService` performs every transition inside one `DB::transaction`, writing a mandatory `AuditLogger::strictLog()` row in the same transaction so an audit-write failure rolls the change back; notifications are synchronous and post-commit (best-effort, never roll back a committed decision). Suspend/reactivate revoke Sanctum tokens, delete DB sessions and null the remember token. Password reset revokes credentials without touching lifecycle state.
- **Guest verified flows (signed, 24h):** email verification uses a GET confirmation page (no state change) plus a separate signed POST that is idempotent and creates no session/token; a signed status page and separate signed resubmit/evidence links serve PENDING/REJECTED applicants. Resend responses are generic (no account enumeration).
- **Enforcement:** new `account.active` middleware (403 JSON for API, logout+redirect for web) is applied to authenticated web and admin groups and to all `auth:sanctum` API groups; the API login issues no token for a non-ACTIVE account. High-impact admin mutations sit behind the framework `password.confirm` middleware; `UserPolicy` denies self-approval/suspension.
- **Provisioning:** `php artisan ajm:create-superadmin` creates an ACTIVE, verified SUPERADMIN, CLI-only (no HTTP route), strictly audited (`SUPERADMIN_PROVISIONED`).
- **B2G evidence:** dedicated `b2g_evidence_submissions` records; version allocation serializes on a parent `users` row lock; prior active versions are marked SUPERSEDED; files are stored on the private `documents` disk and only reachable through an authorization-checked, audited download (audit recorded before bytes are served).
- **Inertia pages added:** `Auth/{Login,Register,ForgotPassword,ResetPassword,ConfirmPassword,VerifyConfirm,AccountStatus,Resubmit,Evidence}.vue`, `Account/Home.vue`, `Admin/Accounts/{Index,Show}.vue`, and `Layouts/AccountLayout.vue`.
- **Verification (local, SQLite in-memory):** full suite `php artisan test` - 716 total, 703 passed, 13 skipped, 0 failed (3,805 assertions), including 52 new tests across Auth/Middleware/Admin/Services/Console/Database. `pint --test` PASS; PHP syntax checks PASS (52 files); `npm run build` PASS; `git diff --check` clean. MySQL coverage was NOT exercised in this pass (SQLite-only run).
- **NOT committed, NOT pushed.** Production activation remains blocked (see MODULES_PROGRESS): first-admin provisioning, SMTP deliverability, B2G evidence policy, migration rollout window, secure-cookie/trusted-proxy config, MFA, and an explicit Sanctum token-lifetime/rotation decision.

## [Account Access & Approval Lifecycle - Pre-Commit Security Review] - 2026-10-13
- **Review scope:** the whole Account Access & Approval change set (13 tracked files plus all new files: enums, models, services, middleware, policies, requests, controllers, notifications, routes, migrations, config, factory, Vue pages, tests). Clean checks: no secrets, no `.env`, no public evidence path, no conflict markers, no unrelated edits; the public seven-field Product API projection, Sanctum contract, and existing rate limiters are untouched.
- **Fixed (confirmed defects):**
  - `NewAccountApplicationNotification` concatenated the `UserRole` backed enum into a string, which throws at render time; the exception was swallowed by the best-effort wrapper, so administrators silently never received new-application mail whenever an applicant requested `BUYER_B2G`. Now uses `?->value`.
  - `NewAccountApplicationNotification` and `AccountStatusChangedNotification` linked to non-existent URLs (`url('/admin/accounts')`, `url('/login')`); corrected to `route('admin.accounts.index')` and `route('login')`.
  - `AccountLifecycleService::approve()` audit `previous_state` now records `requested_role` so approving a B2G-requesting applicant as B2B is explicitly captured.
  - The already-defined `throttle:account-status` limiter is now applied to `GET /status-akun/{user}`.
- **Regression coverage added:** `tests/Feature/Notifications/AccountMailTest.php` renders the lifecycle mail messages directly (not through `Notification::fake()`), so template-time failures fail the suite instead of being swallowed.
- **Reviewed and confirmed sound:** GET verification performs no state change and the POST validates signature/expiry/identity/current-email binding without creating a session or token; `status` is the sole runtime gate and `email_verified_at` is never an authorization gate; `role`/`status`/`requested_role` are not mass-assignable; `account.active` is enforced on all authenticated web and `auth:sanctum` groups (closing pre-suspension tokens); admin mutations require `password.confirm`; self-approval/suspension denied; last-active-superadmin and B2G version allocation serialize through `SELECT ... FOR UPDATE` with DB constraints as backstop; strict audit shares the state-change transaction; evidence lives on the private `documents` disk and downloads are authorized and audited before bytes are served; the migration grandfathers legacy rows as ACTIVE without fabricating `email_verified_at`.
- **Verification:** SQLite full suite 719 total / 706 passed / 13 skipped / 0 failed (3,810 assertions); **MySQL 8.4.3 full suite on the isolated `pengadaan_barang_ajm_testing` database 719 / 719 passed / 0 skipped / 3,847 assertions**. `pint` PASS; PHP syntax 53/53; `npm run build` PASS; `git diff --check` clean.
- **Remaining limitations (unchanged):** SMTP deliverability unverified; first-SUPERADMIN provisioning path on shared hosting unconfirmed; B2G evidence policy/retention pending approval; migration rollout window/backup; secure-cookie/trusted-proxy config; MFA for SUPERADMIN; explicit Sanctum token-lifetime decision; registration password minimum is 8 for public self-service while privileged CLI provisioning requires 12 — a deliberate policy difference (F5), now documented in code, not an oversight.
- **NOT committed, NOT pushed.**
