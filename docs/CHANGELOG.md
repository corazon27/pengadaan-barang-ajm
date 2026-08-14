# Project Changelog

## [Phase B - Foundation Hardening & Reliability Remediation] - 2026-08-13
- **AUTH-2 (High):** native login rate limiting via `RateLimiter::for('login')` in `AppServiceProvider::boot()` — 5 attempts/min per email+IP with a 429 JSON envelope and `LOGIN_THROTTLED` audit row. (Not in `bootstrap/app.php`: the middleware closure runs before the cache provider, so it fatals.)
- **SEC-2 (High):** `UserSeeder` is now fail-closed in production — requires `SEED_DEMO_USERS=true` and the `SEED_ADMIN_PASSWORD`/`SEED_BUYER_B2B_PASSWORD`/`SEED_BUYER_B2G_PASSWORD` env vars, else throws; dev/test stay deterministic. Config via new `app.demo` block; `.env.example` updated; `UserSeederTest` (4 tests) added.
- **AUD-1 (Medium):** audit coverage extended — `USER_LOGIN`, `USER_LOGOUT`, `LOGIN_FAILED`, `LOGIN_THROTTLED`, `PROFILE_UPDATED`, `PRODUCT_CREATED`/`UPDATED`/`DELETED`; `audit_logs.entity_type/entity_id` nullable (migration `2026_08_13_091233_make_audit_log_entity_columns_nullable.php`); `AuditLogger::log()` accepts nullable entities; `AuditLogTest` (7 tests) added.
- **SCHED-1 (Medium):** `CheckOverdueInvoices` now locks selected rows inside the transaction and runs with `->withoutOverlapping()`; idempotency regression test added.
- **PERF-1 (Medium):** new `audit_logs` indexes (`created_at`, `(entity_type, action, created_at)`, `(action, created_at)`) — migration `2026_08_13_091945_add_performance_indexes_to_audit_logs.php`, EXPLAIN-verified on MySQL 8.4.3.
- **QRY-1 / VAL-1 (Medium):** product search now `%term%` and properly grouped inside a `where(...)` closure; pagination clamped to 1..100 (default 15) via `Controller::perPage()` at all 7 listing controllers.
- **P3:** `products.description` nullable at DB level (Option B, migration `2026_08_13_110000_make_product_description_nullable.php`); `PaymentFactory::verified()` sets `verified_by`; `ProductFactory` uses unique slugs; new `UniqueIdentifier` retry-loop service for ORD-/BAST-/INV-/RFQ- numbers.
- **P3 (rollback fixes):** `2026_08_15_add_unique_index_on_orders_rfq_id::down()` drops the backing FK before the unique index; `2026_08_14_add_order_workflow_columns::down()` widens/remaps/narrows the status enum — both reproduced with data on MySQL 8.4.3 and fixed.
- Verified: SQLite suite ✅ **146 / 144 passed / 2 pre-existing skips / 856 assertions**; MySQL 8.4.3 suite ✅ **146 / 146 / 0 skipped / 860 assertions**; `migrate:fresh` + full `migrate:rollback` ✅ on MySQL with data; `pint` ✅; `composer audit` ✅; `route:list` ✅.
- **Gate decision: PASS.** Full report in `docs/PHASE_B_REMEDIATION_REPORT.md`.

## [Phase A - Audit Remediation: Security, Data Integrity & Test Discovery] - 2026-08-15
- **TEST-1 (High):** rewrote `tests/Feature/Api/ProductTest.php` with `test_` prefixes — PHPUnit 12 dropped `/** @test */`, so the class (9 tests) was silently excluded and the earlier "green" suite was a false green. Discovered tests now run.
- **AUTH-1 (High):** `ProductController::destroy` now enforces `ProductPolicy@delete`; non-superadmin `DELETE /products/{product}` → 403 (was 200). Added `test_non_admin_receives_403_on_product_delete`.
- **SEC-1 (Medium):** payment proofs now stored on the private `documents` disk (`payments/proofs/...`) with `proof_file_url` holding the private path (no public URL); added guarded `GET /api/v1/payments/{payment}/proof` streaming endpoint (200/401/403/404). `PaymentTest` 14 → 20 tests.
- **INT-1 (Medium):** migration `2026_08_15_add_unique_invoice_per_order.php` (unique `invoices_order_unique` on `order_id`); `BastController::sign` now runs inside a transaction with `lockForUpdate()` on the order row and audits the locked order. Re-sign → 422, exactly one invoice guaranteed. `InvoiceTest` 8 → 13 tests.
- **INT-2 (Medium):** migration `2026_08_15_relax_orders_top_days_check.php` relaxes the `orders.top_days` CHECK from `> 0` to `>= 0` (MySQL/PostgreSQL) so `IMMEDIATE` (0-day) terms persist; `IMMEDIATE` invoice `due_date = issued_date`. `Module8Test` analytics data restructured for the one-invoice-per-order invariant.
- **INT-3 (High, discovered during MySQL verification):** the `orders.rfq_id` UNIQUE index declared in the create migration was never emitted by Laravel (chained `constrained()` + `unique()`), leaving the one-order-per-RFQ invariant unenforced at the DB level. Added idempotent migration `2026_08_15_add_unique_index_on_orders_rfq_id.php` (both engines); MySQL-only direct-insert concurrency tests now pass.
- Rewrote `OrderTest::test_orders_table_has_unique_index_on_rfq_id` on `Schema::getIndexes()` (Laravel 13 removed `getDoctrineSchemaManager`); now runs on sqlite too. Fixed overdue-invoice test data to keep `due_date >= issued_date` (MySQL `invoices_due_after_issued`).
- Verified: SQLite suite `vendor/bin/phpunit` ✅ **118 tests / 116 passed / 2 skipped / 690 assertions**; MySQL 8.4.3 suite ✅ **118 / 118 / 694 assertions / 0 skipped**; `migrate:fresh --seed` ✅ MySQL; `pint --test` ✅; `composer audit` ✅; `route:list` ✅.
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
- Consolidated `users` schema into the base migration and deleted the data-destroying `update_users_table_for_procurement` migration.
- Enforced enums at DB level (`users.role`, `rfqs.status`, `orders.status`, `invoices.status`) and added 8 driver-guarded CHECK constraints.
- Hardened FKs/columns: composite FK on `invoices(bast_id, order_id)`, unique `orders.rfq_id`, `unsigned` money/quantity/percent, `uuid()` PKs, correct delete rules.
- Removed sensitive/computed fields from `$fillable` (User.role, Rfq.status, Order.total_amount, OrderItem.unit_price/subtotal).
- Server-side price/total computation in `Order`/`OrderItem` hooks (BC Math); model `creating` defaults for enum statuses.
- Enabled strict Eloquent guards (`preventLazyLoading`, `preventSilentlyDiscardingAttributes`, `preventAccessingMissingAttributes`) in non-production.
- Added `declare(strict_types=1)` to controllers, models, seeders, and factories.
- Added 7 factories, idempotent `ProductSeeder`, and `tests/Feature/ModelIntegrityTest` (7 tests / 15 assertions).
- Added test-only `APP_KEY` to `phpunit.xml`.
- Verified: `migrate:fresh --seed` ✅ (MySQL 8.4.3), `php artisan test` ✅ 7/7, `pint --test` ✅.
- Full context in `docs/FOUNDATION_AUDIT_FIXES.md`.

## [Module 1 - API Authentication & User Profile] - 2026-08-12
- Installed `laravel/sanctum` via `php artisan install:api` (v4.3.3) and registered `api:` routing with `apiPrefix('api/v1')` in `bootstrap/app.php`.
- Adapted the Sanctum `personal_access_tokens` migration to `uuidMorphs('tokenable')` to match the UUID primary keys of the `users` table.
- Added `Laravel\Sanctum\HasApiTokens` to the `User` model; login issues a Sanctum token carrying role claims (`role:SUPERADMIN`, `role:BUYER_B2B`, `role:BUYER_B2G`) as token abilities.
- Added uniform API response envelope `{ success, message, data, errors }` via `App\Traits\ApiResponser`; shaped global `ValidationException` (422) and `AuthenticationException` (401) into the same envelope in `bootstrap/app.php`.
- Added auth endpoints: `POST /api/v1/auth/login` (public), `POST /api/v1/auth/logout`, `GET /api/v1/auth/me`, `PUT /api/v1/auth/profile` (all `auth:sanctum`).
- Added `LoginRequest` / `UpdateProfileRequest` (`app/Http/Requests/Auth`), `UserResource`, `AuthController`, `ProfileController`, and `UserPolicy` (self or superadmin) with automatic policy discovery.
- Added `tests/Feature/Api/AuthTest` (8 tests: login success/token abilities, invalid credentials, validation, `me`, profile update, role/email immutability, logout revocation, unauthenticated access).
- Verified: `pint --test` ✅, `php artisan test` ✅ 15/15 (66 assertions), live smoke test on MySQL ✅ (login/me/update/logout/revoked-token → 401).

## [Module 2 - Product Catalog API] - 2026-08-13
- Introduced `Product` model with UUID primary key and extensive attribute set.
- Created `ProductFactory` with superadmin role capability for seeding.
- Developed `StoreProductRequest` and `UpdateProductRequest` with comprehensive validation, including uniqueness checks for `sku` and `slug`.
- Implemented `ProductResource` for clean API output serialization.
- Added `ProductPolicy` enforcing public read access and superadmin‑only CRUD permissions.
- Built `ProductController` with:
  - `index` (public search/filter pagination),
  - `show` (public detail view),
  - `store`/`update`/`destroy` (protected by `auth:sanctum` and `ProductPolicy`).
- Added routes under `/api/v1/products` (public list/show, protected CRUD).
- Wrote `ProductTest` covering public filtering, private CRUD, and role‑based access control.
- Verified live MySQL flow: product creation, update, delete by superadmin, and 403 enforcement for non‑admin.

## [Module 3 - RFQ Workflow API] - 2026-08-13
- Extended `RfqStatus` enum with `QUOTED` and `CANCELLED`; added `valid_until`/`admin_notes` to `rfqs` and `target_price`/`notes` to `rfq_items` via migration.
- Added `RfqItemResource` and `RfqResource` with computed totals and nested product details.
- Created `StoreRfqRequest` (items with product_id, quantity, target_price), `RespondRfqRequest` (Superadmin: offered_price, valid_until), `UpdateRfqStatusRequest`.
- Implemented `RfqPolicy`: public read isolation (buyers see own RFQs), Superadmin full access, owner status transitions (SUBMITTED/QUOTED → CANCELLED; QUOTED → APPROVED/REJECTED/CANCELLED), Superadmin-only quotation response.
- Built `RfqController` with transactional `store` (RFQ + items), `respond` (updates `negotiated_price`, sets `QUOTED`, `valid_until`), `updateStatus` (validated transitions).
- Added routes under `/api/v1/rfqs`: `GET` (list), `POST` (create), `GET {rfq}` (show), `POST {rfq}/respond` (Superadmin), `PATCH {rfq}/status` (owner/Superadmin).
- Added `RfqTest` (12 tests): buyer CRUD isolation, Superadmin list/respond, owner accept/reject/cancel, Superadmin-only respond, invalid transition 422, 403 enforcement.
- Verified: `pint --test` ✅, `php artisan test` ✅ 27/27 (123 assertions).

## [Module 4 - Order & Invoicing Workflow API] - 2026-08-14
- Replaced `OrderStatus` enum with the lifecycle set `PENDING_PAYMENT`, `PROCESSING`, `SHIPPED`, `DELIVERED`, `COMPLETED`, `CANCELLED`; created `BastStatus` enum (`PENDING_SIGNATURE`, `SIGNED`).
- Added `database/migrations/2026_08_14_add_order_workflow_columns.php`: MySQL-guarded ALTER of `orders.status` enum; `bast_documents` gains `status`, `signed_by`, `signed_at`, `notes` and nullable `bast_document_url`/`signed_date`; `invoices` gains `subtotal`, `tax_amount`, `grand_total`.
- Extended `Order`, `BastDocument`, and `Invoice` models with the new fields/casts and `statusLabel()` helpers.
- Added `OrderController` (list/show, RFQ→order conversion, status transitions), `BastController` (show/sign), `InvoiceController` (list/show/payment-status).
- Conversion (`POST /orders`) requires an `APPROVED` unconverted RFQ; items are copied at the quoted `negotiated_price`; RFQ moves to `CONVERTED_TO_ORDER`.
- Status state machine: forward moves Superadmin-only, cancellation allowed for owner/Superadmin; invalid transitions → 422. `DELIVERED` auto-generates a `PENDING_SIGNATURE` BAST; signing the BAST completes the order and auto-generates an UNPAID invoice (subtotal/tax/grand_total, due in `top_days`).
- Added `OrderPolicy`, `BastDocumentPolicy`, `InvoicePolicy`; `InvoiceResource` exposes `payment_status`; Superadmin-only invoice payment-status updates (sets `paid_at` on PAID).
- Registered order/BAST/invoice routes under `/api/v1`.
- Added `OrderTest` (10 tests) and `InvoiceTest` (8 tests) covering conversion rules, role isolation, status transitions, BAST generation/signing, invoice generation, and payment-status RBAC.

## [Audit - Security, Concurrency & Precision Fixes] - 2026-08-14
- **TOCTOU race condition fixed** in RFQ→Order conversion (`OrderController::store`): `lockForUpdate()` on the RFQ row inside `DB::transaction()` plus an in-transaction duplicate check; `QueryException` unique-violation is caught and returned as 422.
- **RFQ item ownership enforced** (`RfqController::respond`): a submitted `rfq_item_id` belonging to a different RFQ is now rejected with HTTP 422 (`items.*.rfq_item_id` error) instead of being silently ignored; no partial writes occur.
- **`OrderPolicy::create`** now accepts the target `Rfq` and verifies ownership (`user->is($rfq->user)`) or Superadmin; controller passes the RFQ instance.
- **BC Math precision** confirmed/enforced for all monetary computation in `BastController::generateInvoice()` (per-item tax = subtotal × rate ÷ 100, summed, then grand total; 2-decimal scale).
- **Tests added**: duplicate RFQ→order conversion rejected (422), DB unique constraint on `orders.rfq_id` verified via direct insert (MySQL), foreign `rfq_item_id` rejection with no partial writes, and valid-item regression test. Concurrency/direct-insert tests skip on SQLite (in-memory DB does not reliably enforce unique constraints).
- Verified: `pint` ✅, `php artisan test` ✅ 49 passed / 3 skipped (SQLite-only skips), 239 assertions.

## [Module 5 - Payment Processing & Payment Terms (TOP / Tax Handling) API] - 2026-08-14
- Added `PaymentTerm` enum (`IMMEDIATE`, `TOP_14`, `TOP_30`, `TOP_60`) with day counts; `invoices.payment_term` snapshot + auto `due_date = issued_date + term.days()` at issuance.
- Renamed `invoices.tax_amount` → `ppn_amount`; added `invoices.pph_amount`, `invoices.payment_term`, and nullable `products.pph_rate_percentage` via migration `2026_08_14_add_payment_processing.php`; created `payments` table.
- Tax breakdown exposed in `InvoiceResource`: `subtotal`, `ppn_amount`, `pph_amount`, `grand_total`, `payment_term`; per-item PPN/PPh via BC Math; **grand_total = subtotal + PPN** (PPh is a withholding, recorded separately).
- Created `payments` ledger: buyer submits proof (`amount`, `payment_method`, `payment_date`, `proof_file` ≤5MB image/PDF, `notes`) → `PENDING_VERIFICATION`; Superadmin lists (status filter) and verifies/rejects.
- Reconciliation on verify (transaction + `lockForUpdate`): Σ VERIFIED ≥ `grand_total` → `PAID` + `paid_at`; partial → `PARTIALLY_PAID`; none → `UNPAID`. `InvoiceStatus` gains `PARTIALLY_PAID`.
- Added `SubmitPaymentRequest`, `VerifyPaymentRequest` (rejection reason required when rejecting), `PaymentResource`, `PaymentPolicy` (owner submit, Superadmin verify), `PaymentController` (`store`/`index`/`verify`).
- Registered routes: `POST /invoices/{invoice}/payments`, `GET /payments`, `PATCH /payments/{payment}/verify`.
- Added `PaymentTest` (14 tests): upload validation, owner isolation (403), full/partial/multi-payment reconciliation to `PAID`/`PARTIALLY_PAID`, rejection with reason, already-settled 422, RBAC, listing scoping, resource breakdown; `InvoiceTest` updated for the rename + PPh test.
- Verified: `pint` ✅, `php artisan test` ✅ 64 passed / 3 skipped (SQLite-only skips), 384 assertions.

## [Module 6 - Notification System (In-App & Email) API] - 2026-08-14
- Added `notifications` table (UUID PK via `uuidMorphs`, `type`, text `data`, nullable `read_at`) via migration `2026_08_14_create_notifications_table.php`; `App\Models\Notification` extends `DatabaseNotification` + `HasUuids` with an `isRead()` helper.
- Added 4 queued notification classes (`ShouldQueue`, `database` + `mail` channels, Indonesian `MailMessage`): `RfqSubmittedNotification` (to all SUPERADMINs), `RfqRespondedNotification` (to RFQ owner), `OrderShippedNotification` (to buyer on `SHIPPED`), `PaymentVerifiedNotification` (to buyer on verification with reconciled `PAID`/`PARTIALLY_PAID` status). All triggers fire after the enclosing `DB::transaction` commits.
- Added `NotificationResource` (`id`, `type` basename, `title`, `message`, `action_url`, `read_at`, `is_read`, `created_at`) and `NotificationController` (`index` with `data.items` + `data.pagination`, `markAsRead`, `readAll`); `NotificationPolicy` limits view to SUPERADMIN/recipient and read-marking to the recipient.
- Registered routes: `GET /notifications`, `PATCH /notifications/{notification}/read`, `POST /notifications/read-all` (all `auth:sanctum`).
- Wired dispatch triggers in `RfqController` (`store`, `respond`), `OrderController` (`updateStatus` on `SHIPPED`), and `PaymentController` (`verify` on `VERIFIED`).
- Added `NotificationTest` (8 tests: newest-first list + read indicator, pagination meta, mark-one-read, read-all, 403 cross-user, 401 unauthenticated, real database-channel dispatch, per-buyer isolation); extended `RfqTest`/`OrderTest`/`PaymentTest` with `Notification::fake()` + `assertSentTo`.
- Verified: `pint` ✅, `php artisan test` ✅ 72 passed / 3 skipped (SQLite-only skips), 453 assertions.
## [Module 7 - Document Engine (PDF Generation)] - 2026-08-13
- Installed `barryvdh/laravel-dompdf` (v3.1.2, pure PHP) for PDF rendering; added a private local `documents` disk (`storage/app/private/documents`) to `config/filesystems.php`.
- Added `config/company.php` letterhead config (name, legal_entity, nib, pkp, npwp, address, phone, email, website, bank details), all env-driven.
- Added `app/Services/PdfService.php` (`generate()` renders a Blade view, stores the PDF on the documents disk, returns the relative path or an empty string + `report()` on failure; `sanitizeFilename()` for safe download filenames).
- Added `app/Http/Controllers/Api/Document/DocumentController.php` with streaming download endpoints: `GET /rfqs/{rfq}/quotation.pdf`, `GET /orders/{order}/bast.pdf`, `GET /invoices/{invoice}/pdf` - `application/pdf` inline responses, 404 `Dokumen belum tersedia.` when the file is missing, authorized via `RfqPolicy`/`BastDocumentPolicy`/`InvoicePolicy`.
- Created Blade templates `resources/views/pdf/{partials/styles, partials/kop-surat, rfq-quotation, bast, invoice}.blade.php` - A4 dompdf-compatible layout, letterhead header, item tables, tax summary, signature blocks, bank transfer details, Indonesian dates (`translatedFormat('j F Y')`) and `1.234.567,89` money formatting.
- Wired generation hooks (run after the success transaction; PDF failure is logged and the business action still succeeds): quotation PDF on `RfqController::respond` (`rfqs.quotation_pdf_url`); BAST draft PDF when the order transitions to `SHIPPED` (`bast_documents.bast_document_url`); invoice PDF on BAST sign (`invoices.invoice_pdf_url`).
- Moved `BastDocument` creation from the `DELIVERED` to the `SHIPPED` branch of `OrderController::updateStatus` (state machine `PROCESSING -> SHIPPED -> BAST_SIGNED`); removed the placeholder URL assignments in `BastController`.
- Added `DocumentTest` (10 tests): quotation/BAST/invoice PDF generation + storage (`%PDF` header check on the documents disk), owner download 200 + `application/pdf`, 404 when missing/URL empty, 401 unauthenticated, 403 cross-user, and `config('company')` completeness.
- Verified: `pint` OK, `php artisan test` OK: 82 passed / 3 skipped (SQLite-only skips) / 506 assertions.