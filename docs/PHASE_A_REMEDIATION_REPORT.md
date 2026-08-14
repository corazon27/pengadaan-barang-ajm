# Phase A Remediation Report — Audit Findings Fix

**Project:** Pengadaan Barang AJM (procurement platform)
**Date:** 2026-08-15
**Scope:** Phase A remediation only (the 5 confirmed audit findings + 3 latent MySQL-only defects that blocked the required MySQL verification). Module 9 / Phase B / regulatory compliance audit are explicitly out of scope.
**Gate decision: PASS**

---

## 1. Findings Fixed

| ID | Severity | Finding | Root cause | Fix |
|----|----------|---------|------------|-----|
| TEST-1 | High | `ProductTest` (9 tests) silently skipped; the suite was a **false green** | PHPUnit 12 (`^12.5.12`, installed 12.5.33) dropped `/** @test */` method annotation; only this class used it | Rewrote `tests/Feature/Api/ProductTest.php` with `test_` prefixes + `test` methods; verified RED first (delete-403 got 200), then GREEN |
| AUTH-1 | High | Non-superadmin `DELETE /api/v1/products/{product}` returned **200** and deleted the product | `ProductController::destroy` never invoked `ProductPolicy@delete` | Added `$this->authorize('delete', $product);` → 403 for non-superadmin; added `test_non_admin_receives_403_on_product_delete` (+ update/create 403 guards) |
| SEC-1 | Medium | Payment proof files stored in `storage/app/public` with guessable paths; the resulting public URL was returned in `proof_file_url` | `PaymentController::store` used `$file->store('payments/proofs')` (public disk) and stored the URL | Proofs now stored on the private **`documents`** disk (`payments/proofs/...`); `proof_file_url` holds the private relative path; added guarded `GET /api/v1/payments/{payment}/proof` (`downloadProof()` — authorize `view`, 404 if missing, streams via `Storage::disk('documents')->response()`). Route placed inside `auth:sanctum` |
| INT-1 | Medium | Concurrent BAST signing could create duplicate invoices for one order | `BastController::sign` had no row lock and no DB-level uniqueness on `invoices.order_id` | New migration `2026_08_15_add_unique_invoice_per_order.php` (unique `invoices_order_unique`); `sign()` wraps work in `DB::transaction` with `Order::query()->lockForUpdate()->findOrFail($order->id)` and audits the locked order (AuditLogger snapshots the entity's new state). Re-sign → 422; duplicates impossible |
| INT-2 | Medium | `orders.top_days` CHECK `(top_days > 0)` silently rejected `IMMEDIATE` (0-day) payment terms at the DB level | Constraint added in `2026_08_12_060630_add_check_constraints.php` predates `PaymentTerm::IMMEDIATE` | New migration `2026_08_15_relax_orders_top_days_check.php` (MySQL/PostgreSQL): drops `orders_top_days_positive`, adds `orders_top_days_non_negative CHECK (top_days >= 0)`; `down()` restores. `IMMEDIATE` invoices get `due_date = issued_date` |
| INT-3 | High | One-order-per-RFQ DB invariant silently unenforced on MySQL | `foreignUuid('rfq_id')->constrained()->unique()` never emits the unique index in Laravel (only the FK index is created) — verified via query-log capture | New idempotent migration `2026_08_15_add_unique_index_on_orders_rfq_id.php` (`Schema::getIndexes()` guard, both engines). MySQL `SHOW INDEX` confirms `orders_rfq_id_unique`; the two MySQL-only concurrency/direct-insert tests now pass |
| — | Medium | `OrderTest::test_orders_table_has_unique_index_on_rfq_id` errored on MySQL | `getDoctrineSchemaManager()` was removed in Laravel 13 | Rewritten on `Schema::getIndexes('orders')`; now runs on sqlite **and** MySQL (sqlite skip removed) |
| — | Medium | Overdue-command test data violated `invoices_due_after_issued` (`due_date < issued_date`) on MySQL | Test set `due_date` in the past while factory kept `issued_date = now()` | Test now backdates `issued_date` to `now()-40d` for the overdue-simulating invoices; command logic and assertions unchanged |

*INT-3 and the two test fixes were **discovered during the mandated MySQL 8.4 verification**. They are fixed because (a) they blocked a clean MySQL run and (b) they are the same data-integrity class the audit already targets. No business behavior was changed.*

---

## 2. Tests Added / Modified

| File | Before | After | New tests |
|------|--------|-------|-----------|
| `tests/Feature/Api/ProductTest.php` | 9 methods (silently skipped) | 9 methods discovered & passing | `test_non_admin_receives_403_on_product_delete`, 403 update/create guards |
| `tests/Feature/Api/PaymentTest.php` | 14 | 20 | `test_payment_proof_is_stored_on_private_documents_disk`, `test_owner_can_download_payment_proof` (200 / `image/jpeg` / body match), `test_superadmin_can_download_any_payment_proof`, `test_non_owner_cannot_download_payment_proof` (403), `test_payment_proof_download_requires_authentication` (401), `test_downloading_missing_payment_proof_returns_404` |
| `tests/Feature/Api/InvoiceTest.php` | 9 | 13 | `test_signing_bast_creates_exactly_one_invoice` (re-sign → 422), `test_order_cannot_have_more_than_one_invoice` (DB rejects), `test_order_accepts_zero_top_days_for_immediate_payment`, `test_immediate_payment_term_sets_due_date_on_issued_date` |
| `tests/Feature/Api/Module8Test.php` | 14 | 14 | analytics test data restructured (PAID invoice moved to its own order; `total_count` 2→3) to satisfy the one-invoice-per-order invariant |
| `tests/Feature/Api/OrderTest.php` | 10 | 10 | doctrine test rewritten on `Schema::getIndexes`; the 2 SQLite-skipped MySQL tests now execute and pass on MySQL |

**New migrations (3):**
- `database/migrations/2026_08_15_add_unique_invoice_per_order.php`
- `database/migrations/2026_08_15_relax_orders_top_days_check.php`
- `database/migrations/2026_08_15_add_unique_index_on_orders_rfq_id.php`

**Production code changed (4):**
- `app/Http/Controllers/Api/Product/ProductController.php` (AUTH-1)
- `app/Http/Controllers/Api/Payment/PaymentController.php` (SEC-1)
- `app/Http/Controllers/Api/Bast/BastController.php` (INT-1)
- `routes/api.php` (SEC-1 proof route)

---

## 3. Real Verification Outputs

**SQLite suite (default `phpunit.xml`, sqlite `:memory:`):**
```
{"tool":"phpunit","result":"passed","tests":118,"passed":116,"assertions":690,"skipped":2,"duration_ms":21338}
```
The 2 skips are the pre-existing SQLite-only concurrency tests (`OrderTest:297`, `OrderTest:358`). Previously 3 skipped — `OrderTest:340` now runs on sqlite too.

**MySQL 8.4.3 suite** (throwaway `pengadaan_barang_ajm_test` DB, created for the run and dropped after):
```
{"tool":"phpunit","result":"passed","tests":118,"passed":118,"assertions":694,"duration_ms":22500}
```
0 skipped; the SQLite-only tests execute and pass on MySQL.

**`php artisan migrate:fresh --seed` on MySQL 8.4.3** — all 18 migrations (incl. the 3 new) + UserSeeder + ProductSeeder: `DONE`.

**Direct MySQL 8.4.3 checks** (via PDO, cleaned up after):
- `invoices_order_unique` present; duplicate insert → `1062 Duplicate entry ... for key 'invoices.invoices_order_unique'`
- `orders_top_days_non_negative` → `(\`top_days\` >= 0)`; old `orders_top_days_positive` gone; `top_days = 0` insert accepted; negative rejected
- `orders_rfq_id_unique` present (unique on `rfq_id`); MySQL now reuses it for the FK

**`vendor/bin/pint --test`** on all 13 touched files: `passed` (one auto-fix: `fully_qualified_strict_types` / `ordered_imports` in `InvoiceTest.php`, re-run green).

**`php artisan route:list`** — confirms `GET|HEAD api/v1/payments/{payment}/proof .. PaymentController@downloadProof` registered inside `auth:sanctum`.

**`composer audit`** — `No security vulnerability advisories found.`

---

## 4. Remaining Findings (deferred — not in Phase A scope)

**P2 (should fix in Phase B):**
- **QRY-1** product search: `where('title','like','%q%')->orWhere('sku',...)` and no index on `title` — leading-wildcard search cannot use an index.
- **VAL-1** `products.description` nullable in model but `NOT NULL` in schema (factory/test seeding only).
- **INT-3** no stock check/decrement on order conversion or payment settlement; stock can go negative.
- **INT-4** payment overpayment has no cap; `Σ VERIFIED` far above `grand_total` is not rejected.
- **INT-5** `Order::saving` casts totals via `(float)` — reintroduces FP drift on already-BC-Math values (store precision).
- **INT-6** `InvoiceController::updatePaymentStatus` allows arbitrary transitions and takes no row lock.
- **INT-7** `products.tax_rate_percentage` / `pph_rate_percentage` are not snapshotted onto invoice lines; later product edits change invoice tax history.
- **AUTH-2** no login rate limit and no token expiry for Sanctum tokens.
- **SEC-2** `UserSeeder` uses `password123`; no forced-rotation/flag.
- **AUD-1** audit gaps: login/logout, product CRUD, profile update, `PAYMENT_REJECTED` not currently audited.
- **SCHED-1** `CheckOverdueInvoices` runs one large transaction without row locks (long-running + contention risk at scale).
- **PERF-1** missing indexes on `audit_logs(entity_type,entity_id)` and `payments(invoice_id,status)`.
- **CFG-1** `.env.example` may contain placeholder credentials (verify before deployment).

**P3 (low):**
- `Str::random(10)` business-number collision risk (order_number, bast_number, invoice_number).
- `PaymentFactory::verified` doesn't set `verified_by`; `ProductFactory::slug` non-unique.
- Broken `down()` in `2026_08_14_add_order_workflow_columns.php` (MySQL).
- `OrderController::store` uses `QueryException` as control flow (works, but heavy).
- No `PaymentRejectedNotification`; `InvoiceResource::paid_amount` issues an extra query per row; public `ProductResource` exposes pricing; unbounded `per_page`; timestamps stored in UTC; `UserResource` exposes PDP-relevant fields.

---

## 5. Documentation Changes

- `docs/MODULES_PROGRESS.md` — Module 2 (AUTH-1 note), Module 4 (InvoiceTest count), Module 5 (proof route + SEC-1 note, PaymentTest count), new **Phase A** section with findings table, verification evidence, remaining findings, and gate decision; Module 8 verified counts refreshed.
- `docs/CHANGELOG.md` — new top entry `[Phase A - Audit Remediation ...] 2026-08-15`.

---

## 6. Gate Decision

**PASS.**

Justification:
- All 5 confirmed Phase A findings fixed with tests, plus 3 latent MySQL-only integrity/test defects discovered during verification.
- The suite is now **genuinely green**: 118 tests / 116 passed / 2 SQLite-only skips on sqlite, and 118 / 118 on MySQL 8.4.3 — with raw command output above.
- All 3 migrations are reversible, non-destructive (the dev DB held only seed data; verified no duplicates before enforcing new constraints), and validated on MySQL 8.4.3.
- Business behavior is preserved (only authorization enforcement, storage location, and DB invariants changed); `pint` clean; `composer audit` clean.
- No open Phase A blockers; P2/P3 items are explicitly deferred and tracked for Phase B / Module 9.
