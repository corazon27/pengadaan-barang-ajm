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
