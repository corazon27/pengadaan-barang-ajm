# Project Changelog

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