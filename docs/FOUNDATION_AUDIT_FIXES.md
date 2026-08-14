# Foundation Audit Fixes & Current Technical State

**Project:** Pengadaan Barang AJM (Laravel procurement platform)
**Phase:** Sprint 1 & 2 — Foundation & Data Integrity Remediation
**Date:** 2026-08-12
**Scope:** Migrations, Models, Factories, Seeders, Application Strictness, Test Coverage

---

## 1. Executive Summary

### Sprint 1 — Foundation Remediation
- Consolidated the `users` schema into the base Laravel migration and **deleted** the obsolete `2026_08_12_060529_update_users_table_for_procurement.php` (a later-batch migration that re-created `users` with `dropIfExists`, destroying all rows in the process).
- Restored all procurement columns directly in `0001_01_01_000000_create_users_table.php`: `uuid` PK, `role` (enum, default `BUYER_B2B`), `company_name`, `npwp_number`, `address`, `phone_number`, plus `email_verified_at` / `remember_token` / `sessions` / `password_reset_tokens`.
- Enforced enum value ranges at the database level using `->enum(...)` for `users.role`, `rfqs.status`, `orders.status`, and `invoices.status`.
- Removed sensitive/internally-managed fields from `$fillable` to block mass assignment (see §3.2).
- Moved price/total computation into model `saving`/`saved`/`deleted` hooks so business values are derived server-side, never client-supplied.
- Enabled Eloquent strictness guards in non-production environments.

### Sprint 2 — Integrity & Test Coverage Remediation
- Added database-level `CHECK` constraints for financial and inventory ranges (8 constraints, driver-guarded for SQLite).
- Hardened foreign keys: composite FK on `invoices(bast_id, order_id) → bast_documents(id, order_id)`, unique `orders.rfq_id`, `unsigned` money/quantity/percentage columns, `uuid()` primary keys, and `restrictOnDelete`/`nullOnDelete`/`cascadeOnDelete` rules.
- Added 7 factories to complete coverage for every model.
- Added `tests/Feature/ModelIntegrityTest.php` (5 scenarios / 15 assertions).
- Added a test-only `APP_KEY` to `phpunit.xml` so `php artisan test` boots without a `.env` key.

### Verification Status

| Command | Result |
|---|---|
| `php artisan migrate:fresh --seed` (MySQL 8.4.3, Laragon) | ✅ Passed — 10 migrations applied, 8 CHECK constraints created, seeders ran |
| `php artisan test` | ✅ 7 tests passed / 15 assertions |
| `vendor/bin/pint --test` | ✅ Passed — no style violations |

---

## 2. File Modifications Registry

### Created
| File | Purpose |
|---|---|
| `database/migrations/2026_08_12_060630_add_check_constraints.php` | 8 driver-guarded CHECK constraints (financial/inventory integrity) |
| `database/factories/RfqFactory.php` | Rfq factory (status default via model `creating` hook) |
| `database/factories/RfqItemFactory.php` | RfqItem factory |
| `database/factories/OrderFactory.php` | Order factory (status `DRAFT`, `top_days` 30) |
| `database/factories/OrderItemFactory.php` | OrderItem factory (price derived via model hook) |
| `database/factories/BastDocumentFactory.php` | BastDocument factory |
| `database/factories/InvoiceFactory.php` | Invoice factory (bast auto-created against the same order) |
| `database/factories/AuditLogFactory.php` | AuditLog factory |
| `tests/Feature/ModelIntegrityTest.php` | Integrity regression suite |
| `docs/FOUNDATION_AUDIT_FIXES.md` | This document |

### Updated
| File | Change |
|---|---|
| `database/migrations/0001_01_01_000000_create_users_table.php` | Role/company columns consolidated; `role` as `enum`, default `BUYER_B2B` |
| `database/migrations/2026_08_12_060550_create_products_table.php` | `unsigned` percentages/stock, `uuid()` PK |
| `database/migrations/2026_08_12_060557_create_rfqs_and_rfq_items_tables.php` | `enum` status, `unsigned` quantity, `restrictOnDelete`, `uuid()` PK |
| `database/migrations/2026_08_12_060604_create_orders_and_order_items_tables.php` | `enum` status, unique `rfq_id`, `unsigned` `top_days`/`quantity`, `uuid()` PK |
| `database/migrations/2026_08_12_060611_create_bast_documents_table.php` | `uuid()` PK, composite unique `(id, order_id)` for FK support |
| `database/migrations/2026_08_12_060617_create_invoices_table.php` | Composite FK `(bast_id, order_id)` → `bast_documents`, `enum` status |
| `database/migrations/2026_08_12_060623_create_audit_logs_table.php` | `uuid()` PK, `nullOnDelete` user, composite index `(entity_type, entity_id)` |
| `app/Models/User.php` | Removed `role` from `$fillable`; `creating` default `BUYER_B2B`; `casts()` method |
| `app/Models/Product.php` | `casts()` method + `single_blank_line_at_eof` |
| `app/Models/Rfq.php` | `status` out of `$fillable`; `creating` default `SUBMITTED`; `casts()` method |
| `app/Models/Order.php` | `total_amount` out of `$fillable`; server-side total recompute hooks; `casts()` method |
| `app/Models/OrderItem.php` | `unit_price`/`subtotal` out of `$fillable`; derive + recompute hooks; `casts()` method |
| `app/Models/Invoice.php` | `creating` default `UNPAID`; `casts()` method |
| `app/Providers/AppServiceProvider.php` | Strict Eloquent guards enabled |
| `app/Http/Controllers/Controller.php` | `declare(strict_types=1)` added |
| `database/factories/UserFactory.php` | Role state via `afterCreating` + `forceFill` (`superadmin`, `buyerB2b`, `buyerB2g`) |
| `database/factories/ProductFactory.php` | `declare(strict_types=1)` + `concat_space` style |
| `database/seeders/UserSeeder.php` | `forceFill` for role assignments; `declare(strict_types=1)` |
| `database/seeders/ProductSeeder.php` | Idempotent factory block; `declare(strict_types=1)` |
| `phpunit.xml` | Test-only `APP_KEY` added |
| `docs/CHANGELOG.md` | Sprint summary appended |

### Deleted
| File | Reason |
|---|---|
| `database/migrations/2026_08_12_060529_update_users_table_for_procurement.php` | Obsolete: re-created `users` via `dropIfExists` in a later batch (data-loss risk). Columns consolidated into the base migration. |

---

## 3. Detailed Breakdown of Changes

### 3.1 Database & Migrations

**User migration consolidation**
- Previously: the base `create_users_table` migration created a minimal `users` table and a second migration `..._update_users_table_for_procurement` ran `dropIfExists('users')` then re-created the table with procurement columns. Running the second migration **destroyed all user rows**.
- Now: the base migration defines the full procurement schema once, and the update migration is deleted. `role` is a real `enum('SUPERADMIN','BUYER_B2B','BUYER_B2G')` with default `BUYER_B2B`.

**Database-level enum enforcement**
- `users.role`, `rfqs.status`, `orders.status`, `invoices.status` are declared with `$table->enum(...)` backed by the enum `cases()` — the DB rejects out-of-range values, independent of application validation.

**Driver-guarded CHECK constraints** (`2026_08_12_060630_add_check_constraints.php`)
- The migration short-circuits on SQLite (`DB::getDriverName() === 'sqlite'`) because SQLite cannot `ALTER TABLE ... ADD CONSTRAINT`; the `unsigned` columns still prevent negatives there.
- Applied constraints (MySQL 8.0.16+):

| Constraint | Table | Rule |
|---|---|---|
| `products_margin_range` | `products` | `margin_percentage BETWEEN 0 AND 100` |
| `products_tax_rate_range` | `products` | `tax_rate_percentage BETWEEN 0 AND 100` |
| `products_tkdn_range` | `products` | `tkdn_percentage IS NULL OR tkdn_percentage BETWEEN 0 AND 100` |
| `products_stock_non_negative` | `products` | `stock >= 0` |
| `rfq_items_quantity_positive` | `rfq_items` | `quantity > 0` |
| `order_items_quantity_positive` | `order_items` | `quantity > 0` |
| `orders_top_days_positive` | `orders` | `top_days > 0` |
| `invoices_due_after_issued` | `invoices` | `due_date >= issued_date` |

All 8 constraints verified present in `information_schema.TABLE_CONSTRAINTS` on MySQL 8.4.3.

**Foreign key & column integrity**
- `uuid()` primary keys on every table; `HasUuids` on every model.
- `orders.rfq_id`: nullable, `nullOnDelete`, and **unique** (an RFQ converts to at most one order).
- `invoices`: composite foreign key `(bast_id, order_id) → bast_documents (id, order_id)`, backed by the composite unique index `bast_documents(id, order_id)`; guarantees an invoice's BAST belongs to the same order as the invoice.
- `invoices.order_id`, `invoices.bast_id`, and `bast_documents.order_id` → `restrictOnDelete` (a BAST/invoice cannot be orphaned by deleting its order).
- Child items (`rfq_items.rfq_id`, `order_items.order_id`) → `cascadeOnDelete` (deleting a header removes its lines).
- `audit_logs.user_id` → nullable + `nullOnDelete` so logs survive user deletion.
- Money/quantity/percentage columns are `unsigned`; quantities are `unsigned` integers.
- Indexes added for query paths: `rfqs(user_id)`, `rfqs(status)`, `orders(user_id)`, `orders(status)`, `invoices(order_id)`, `invoices(due_date)`, `invoices(status)`, `audit_logs(entity_type, entity_id)`.

### 3.2 Models & Business Logic

**Mass-assignment hardening** — sensitive or computed fields removed from `$fillable`:
- `User.role` — role is granted by the backend (seeder/factory via `forceFill`), never accepted from request input.
- `Rfq.status` — workflow state advanced server-side.
- `Order.total_amount` — always derived from line items.
- `OrderItem.unit_price`, `OrderItem.subtotal` — always derived from the product + quantity.

> `Invoice.status` remains fillable by design (invoice lifecycle managed in controllers); DB default `UNPAID` still applies when omitted.

**Server-side calculation logic**
- `Order::booted()` — `saving` hook sets `total_amount = (float) items()->sum('subtotal')`. Public `recalculateTotal()` re-sums and saves quietly (used by item hooks).
- `OrderItem::booted()` — `saving` derives `unit_price` from `products.base_price` when absent (protects against price drift/omission), then computes `subtotal = bcmul(unit_price, quantity, 2)` (BC Math, no float drift). `saved` and `deleted` hooks trigger `Order::recalculateTotal()` so order totals stay consistent on add/update/delete.
- `Rfq::booted()` — `creating` sets `status ??= RfqStatus::SUBMITTED`.
- `User::booted()` — `creating` sets `role ??= UserRole::BUYER_B2B`.
- `Invoice::booted()` — `creating` sets `status ??= InvoiceStatus::UNPAID`.

**Why the `creating` hooks matter:** an attribute backed only by a DB default is absent from the in-memory model after `create()` (Eloquent does not re-read the row). Enum-cast lookups on a missing attribute return `null` instead of the default. The `creating` hooks make the default visible immediately in-memory.

**Dead `#[Fillable]` attributes removed**
- The previous model definitions carried commented-out/deprecated fillable scaffolding. All models now use the modern `casts(): array` method instead of the deprecated `$casts` array property, and only live, reachable attributes remain in `$fillable`.

### 3.3 Factories & Seeders

**UserFactory & role via `forceFill`**
- `role` is not mass-assignable, so the factory assigns it through `afterCreating` + `forceFill(...)->save()`:
  - default → `BUYER_B2B` (also enforced by the model `creating` hook)
  - `superadmin()` → `SUPERADMIN`
  - `buyerB2b()` → `BUYER_B2B`
  - `buyerB2g()` → `BUYER_B2G` + government-style company name

**UserSeeder**
- Uses `updateOrCreate` on `email` for idempotent seeding (Superadmin, Buyer B2B, Buyer B2G) and assigns roles via `forceFill(['role' => ...])->save()` — bypassing mass-assignment protection deliberately, as this is trusted backend seed code.

**ProductSeeder idempotency fix**
- The 3 curated B2G/B2B products use `updateOrCreate(['sku' => ...], $data)`.
- The 10 random factory products are now guarded: `if (Product::where('sku', 'like', 'PRD-%')->doesntExist())` — re-running the seeder no longer duplicates samples.

**New factories (7)**
- `RfqFactory`, `RfqItemFactory`, `OrderFactory`, `OrderItemFactory`, `BastDocumentFactory`, `InvoiceFactory`, `AuditLogFactory`.
- `InvoiceFactory` self-consistently creates a `BastDocument` against the same `order_id`, satisfying the composite FK.
- `OrderItemFactory` relies on the model hook to derive `unit_price`/`subtotal`.
- `declare(strict_types=1)` on all new/existing factories (pint `concat_space` applied).

### 3.4 Application Strictness & Config

**`AppServiceProvider::boot()`** (non-production only):
- `Model::preventLazyLoading()` — N+1 queries throw instead of silently loading.
- `Model::preventSilentlyDiscardingAttributes()` — non-fillable keys in `create()/fill()` throw `MassAssignmentException`.
- `Model::preventAccessingMissingAttributes()` — accessing an attribute never loaded throws instead of returning `null`.

**Strict types**
- `declare(strict_types=1)` added to `app/Http/Controllers/Controller.php`, all models, seeders, and factories, so scalar type mismatches fail loudly at the boundary.

**`phpunit.xml`**
- Test-only `APP_KEY` added so the suite boots even when `.env` has no key (previously `ExampleTest` failed with *"No application encryption key has been specified"*).

**Note on `preventSilentlyDiscardingAttributes`:** it fires on `create()/fill()` for non-fillable keys. The deliberate, trusted bypasses (factory/seeder `forceFill`) are the only sanctioned uses of `forceFill`.

---

## 4. Current Database Schema & Model State Summary

### Enums (`app/Enums`)
| Enum | Values |
|---|---|
| `UserRole` | `SUPERADMIN`, `BUYER_B2B`, `BUYER_B2G` |
| `RfqStatus` | `SUBMITTED`, `REVIEWED`, `APPROVED`, `REJECTED`, `CONVERTED_TO_ORDER` |
| `OrderStatus` | `DRAFT`, `WAITING_PO`, `PROCESSING`, `SHIPPED`, `BAST_SIGNED`, `INVOICED`, `PAID`, `CANCELLED` |
| `InvoiceStatus` | `UNPAID`, `OVERDUE`, `PAID` |

### Models

#### `User` (Authenticatable · HasUuids · Notifiable)
- **Mass-assignable:** `email`, `password`, `full_name`, `company_name`, `npwp_number`, `address`, `phone_number`
- **Casts:** `password` → hashed; `role` → `UserRole`; `email_verified_at` → datetime
- **Hidden:** `password`, `remember_token`
- **Hooks:** `creating` → role default `BUYER_B2B`
- **Relationships:** `rfqs()`, `orders()`, `auditLogs()`

#### `Product` (HasFactory · HasUuids)
- **Mass-assignable:** `sku`, `title`, `slug`, `description`, `base_price`, `margin_percentage`, `tax_rate_percentage`, `estimated_shipping`, `tkdn_percentage`, `is_sni`, `warranty_info`, `datasheet_url`, `stock`
- **Casts:** `base_price`, `margin_percentage`, `tax_rate_percentage`, `estimated_shipping`, `tkdn_percentage` → decimal:2; `is_sni` → boolean; `stock` → integer
- **Relationships:** `rfqItems()`, `orderItems()`

#### `Rfq` (HasFactory · HasUuids)
- **Mass-assignable:** `rfq_number`, `user_id`, `quotation_pdf_url`, `notes`
- **Casts:** `status` → `RfqStatus`
- **Hooks:** `creating` → status default `SUBMITTED`
- **Relationships:** `user()`, `items()`, `order()`

#### `RfqItem` (HasFactory · HasUuids · `timestamps = false`)
- **Mass-assignable:** `rfq_id`, `product_id`, `quantity`, `negotiated_price`
- **Casts:** `quantity` → integer; `negotiated_price` → decimal:2
- **Relationships:** `rfq()`, `product()`

#### `Order` (HasFactory · HasUuids)
- **Mass-assignable:** `order_number`, `user_id`, `rfq_id`, `status`, `top_days`, `po_document_url`, `lkpp_product_url`
- **Casts:** `status` → `OrderStatus`; `top_days` → integer; `total_amount` → decimal:2
- **Hooks:** `saving` → recompute `total_amount` from items; `recalculateTotal()` public helper
- **Relationships:** `user()`, `rfq()`, `items()`, `bastDocument()`, `invoices()`

#### `OrderItem` (HasFactory · HasUuids · `timestamps = false`)
- **Mass-assignable:** `order_id`, `product_id`, `quantity`
- **Casts:** `quantity` → integer; `unit_price`, `subtotal` → decimal:2
- **Hooks:** `saving` → derive `unit_price` from product, compute `subtotal` via `bcmul`; `saved`/`deleted` → `Order::recalculateTotal()`
- **Relationships:** `order()`, `product()`

#### `BastDocument` (HasFactory · HasUuids · `UPDATED_AT = null`)
- **Mass-assignable:** `order_id`, `bast_number`, `bast_document_url`, `signed_date`
- **Casts:** `signed_date` → date
- **Relationships:** `order()`, `invoices()`

#### `Invoice` (HasFactory · HasUuids · `UPDATED_AT = null`)
- **Mass-assignable:** `order_id`, `bast_id`, `invoice_number`, `faktur_pajak_number`, `invoice_pdf_url`, `faktur_pajak_url`, `amount_due`, `issued_date`, `due_date`, `status`, `paid_at`
- **Casts:** `status` → `InvoiceStatus`; `issued_date`, `due_date` → date; `paid_at` → datetime; `amount_due` → decimal:2
- **Hooks:** `creating` → status default `UNPAID`
- **Relationships:** `order()`, `bastDocument()`

#### `AuditLog` (HasFactory · HasUuids · `UPDATED_AT = null`)
- **Mass-assignable:** `user_id`, `action`, `entity_type`, `entity_id`, `previous_state`, `new_state`
- **Casts:** `previous_state`, `new_state` → array (JSON columns)
- **Relationships:** `user()`

### Schema-at-a-glance (tables / PK / key constraints)
| Table | PK | Notable constraints |
|---|---|---|
| `users` | `uuid` | `email` unique; `role` enum default `BUYER_B2B` |
| `products` | `uuid` | `sku`, `slug` unique; 4 CHECK constraints; unsigned money/percent/stock |
| `rfqs` | `uuid` | `rfq_number` unique; `user_id` FK restrict; `status` enum default `SUBMITTED` |
| `rfq_items` | `uuid` | `rfq_id` FK cascade; `product_id` FK restrict; `quantity` unsigned + CHECK > 0 |
| `orders` | `uuid` | `order_number` unique; `rfq_id` unique FK nullOnDelete; `user_id` FK restrict; `status` enum default `DRAFT`; CHECK `top_days > 0` |
| `order_items` | `uuid` | `order_id` FK cascade; `product_id` FK restrict; `quantity` unsigned + CHECK > 0 |
| `bast_documents` | `uuid` | `bast_number` unique; `order_id` FK restrict; unique `(id, order_id)` |
| `invoices` | `uuid` | `invoice_number` unique; composite FK `(bast_id, order_id)` restrict; `status` enum default `UNPAID`; CHECK `due_date >= issued_date` |
| `audit_logs` | `uuid` | `user_id` FK nullOnDelete; index `(entity_type, entity_id)` |

### Cross-cutting conventions (for all future code)
- **IDs:** `HasUuids` — never trust sequential IDs.
- **Money:** `decimal(15,2)`, computed with `bcmul`/BC Math; formatted via `decimal:2` casts.
- **Enums:** DB `enum()` + PHP backed-enum cast; add new states to **both** the enum and any migration before use.
- **Mass assignment:** keep business/sensitive columns out of `$fillable`; use `forceFill` only in trusted backend code (factories/seeders/services).
- **Strictness:** `preventLazyLoading`, `preventSilentlyDiscardingAttributes`, `preventAccessingMissingAttributes` are ON outside production — eager load relations and fill only fillable keys.
- **Deletes:** respect per-FK rules — orders cannot be deleted while a BAST/invoice references them; deleting an order cascades to its items only.
- **Tests:** `ModelIntegrityTest` is the regression gate for the above invariants.

---

*Next milestone (per plan): Controllers, Form Requests, and API routes — all of which must respect the mass-assignment and server-side-computation conventions above.*

---

## 5. Phase B Addendum — Foundation Hardening & Reliability (2026-08-13)

Phase B resolved the approved P2/P3 findings from the Phase A audit. Summary of schema-adjacent deltas that extend the state described above; see `docs/PHASE_B_REMEDIATION_REPORT.md` for full evidence.

| Area | Change |
|---|---|
| `audit_logs` | `entity_type` / `entity_id` now nullable (migration `2026_08_13_091233_make_audit_log_entity_columns_nullable.php`) for system/identity audit events; added `created_at`, `(entity_type, action, created_at)`, `(action, created_at)` indexes (migration `2026_08_13_091945_add_performance_indexes_to_audit_logs.php`) |
| `products` | `description` is now nullable at the DB level (Option B; migration `2026_08_13_110000_make_product_description_nullable.php`). `datasheet_url` was already `string(500)` — confirmed, no change |
| `orders` / `bast_documents` / `invoices` | Business numbers (`ORD-`, `BAST-`, `INV-`, `RFQ-`) now generated through `App\Services\UniqueIdentifier` (retry loop against the unique column) |
| Migration rollback | `2026_08_15_add_unique_index_on_orders_rfq_id::down()` drops the backing FK before the unique index; `2026_08_14_add_order_workflow_columns::down()` widens the status enum, remaps newer statuses to legacy values, then narrows |
| Seeders / factories | `UserSeeder` fail-closed in production (`SEED_DEMO_USERS` + `SEED_ADMIN_PASSWORD` / `SEED_BUYER_B2B_PASSWORD` / `SEED_BUYER_B2G_PASSWORD`); `PaymentFactory::verified()` sets `verified_by`; `ProductFactory` uses unique slugs |
| Application | Native login rate limiter (`AppServiceProvider::boot()`), pagination clamp 1..100 via `Controller::perPage()`, grouped `%term%` product search |

New/extended tests: `tests/Feature/UserSeederTest.php`, `tests/Feature/AuditLogTest.php`, plus additions to `tests/Feature/Api/{AuthTest,ProductTest,Module8Test}.php`.

