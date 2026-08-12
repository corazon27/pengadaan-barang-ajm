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