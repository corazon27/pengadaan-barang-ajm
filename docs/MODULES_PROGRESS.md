# Technical Progress: API Modules

---

## Module 1 — Authentication & User Profile API

### Implemented Routes & Methods
| Method | Endpoint | Guard | Description |
|--------|----------|-------|-------------|
| POST | `/api/v1/auth/login` | public | Authenticate credentials, issue Sanctum token with role abilities |
| POST | `/api/v1/auth/logout` | `auth:sanctum` | Revoke current access token |
| GET | `/api/v1/auth/me` | `auth:sanctum` | Return authenticated user profile |
| PUT | `/api/v1/auth/profile` | `auth:sanctum` | Update user profile (role/email immutable) |

### Token Abilities (Role Claims)
- Sanctum tokens are created with abilities: `role:SUPERADMIN`, `role:BUYER_B2B`, `role:BUYER_B2G`.
- Abilities stored in `personal_access_tokens.abilities` JSON column.
- Authorization policies (`UserPolicy`, `ProductPolicy`) check `user->role === UserRole::SUPERADMIN` directly on the model; token abilities enable future API-gateway / middleware checks.

### Envelope Format
All JSON responses (success, validation, auth errors) use the same structure:
```json
{
  "success": boolean,
  "message": string,
  "data": object|null|array,
  "errors": object|null
}
```
- **Success (2xx):** `success=true`, `data` contains payload, `errors=null`
- **Validation (422):** `success=false`, `message="Validasi gagal."`, `data=null`, `errors` = Laravel validation error bag
- **Authentication (401):** `success=false`, `message="Tidak terautentikasi."`, `data=null`, `errors=null`

Global exception handlers registered in `bootstrap/app.php` shape `ValidationException` and `AuthenticationException` into this envelope for `api/*` and JSON requests.

### Policies
- **UserPolicy** (`app/Policies/UserPolicy.php`): `view`, `update` → `actor->is(target) || actor->role === UserRole::SUPERADMIN`
- Auto-discovered by Laravel 13 (no manual registration required).

### Key Files Created/Modified
- `app/Models/User.php` — added `HasApiTokens` trait
- `app/Traits/ApiResponser.php` — `successResponse()`, `errorResponse()`
- `app/Http/Requests/Auth/LoginRequest.php` — email/password validation
- `app/Http/Requests/Auth/UpdateProfileRequest.php` — profile fields only (no role/email)
- `app/Http/Resources/UserResource.php` — public profile fields + role value
- `app/Http/Controllers/Api/Auth/AuthController.php` — login, logout, me
- `app/Http/Controllers/Api/Auth/ProfileController.php` — update profile
- `app/Policies/UserPolicy.php`
- `bootstrap/app.php` — `apiPrefix('api/v1')`, global exception envelope handlers
- `routes/api.php` — auth routes under `/api/v1/auth`
- `tests/Feature/Api/AuthTest.php` — 8 tests (login, invalid creds, validation, me, profile update, role/email immutability, logout revocation, unauthenticated access)
- `database/migrations/2026_08_12_072420_create_personal_access_tokens_table.php` — UUID morphs for tokenable

---

## Module 2 — Product Catalog API

### Implemented Routes & Methods
| Method | Endpoint | Guard | Description |
|--------|----------|-------|-------------|
| GET | `/api/v1/products` | public | Paginated, filterable product listing |
| GET | `/api/v1/products/{product}` | public | Single product by ID or slug |
| POST | `/api/v1/products` | `auth:sanctum` + `ProductPolicy@create` | Create product (Superadmin only) |
| PUT/PATCH | `/api/v1/products/{product}` | `auth:sanctum` + `ProductPolicy@update` | Update product (Superadmin only) |
| DELETE | `/api/v1/products/{product}` | `auth:sanctum` + `ProductPolicy@delete` | Delete product (Superadmin only) |

### Filtering Parameters (`GET /api/v1/products`)
| Parameter | Type | Description |
|-----------|------|-------------|
| `search` | string | Case-insensitive partial match on `title` OR `sku` |
| `is_sni` | boolean | Filter by SNI certification (`1` = true) |
| `min_tkdn` | integer | Minimum TKDN percentage (>=) |
| `in_stock` | boolean | Only products with `stock > 0` (`1` = true) |
| `per_page` | integer | Pagination size (default 15) |
| `page` | integer | Page number |

Example: `/api/v1/products?search=laptop&is_sni=1&min_tkdn=40&in_stock=1&per_page=20`

### RBAC Policy (`ProductPolicy`)
- `viewAny`, `view` → **public** (any authenticated or unauthenticated user)
- `create`, `update`, `delete` → **`UserRole::SUPERADMIN` only**
- Enforced in controller via `$this->authorize('create', Product::class)` (or `update`/`delete` with model instance).

### Key Files Created/Modified
- `app/Models/Product.php` — model with UUID PK, fillable attributes, decimal casts
- `database/migrations/2026_08_12_060700_create_products_table.php` — products table (sku, title, slug, description, base_price, margin_percentage, tax_rate_percentage, estimated_shipping, tkdn_percentage, is_sni, warranty_info, datasheet_url, stock)
- `database/factories/ProductFactory.php` — realistic product data generation
- `app/Http/Requests/Product/StoreProductRequest.php` — create validation (sku/slug unique, numeric ranges, stock >= 0)
- `app/Http/Requests/Product/UpdateProductRequest.php` — update validation (unique ignoring current ID)
- `app/Http/Resources/Product/ProductResource.php` — API output serialization
- `app/Policies/ProductPolicy.php` — public read, superadmin CRUD
- `app/Http/Controllers/Api/Product/ProductController.php` — index, show, store, update, destroy
- `routes/api.php` — product routes under `/api/v1/products`
- `tests/Feature/Api/ProductTest.php` — 9 tests (public filtering, single product, superadmin CRUD, 403 for non-admin)
- `database/factories/UserFactory.php` — added `superadmin()`, `buyerB2b()`, `buyerB2g()` states

---

## Module 3 — RFQ Workflow API

### Implemented Routes & Methods
| Method | Endpoint | Guard | Description |
|--------|----------|-------|-------------|
| GET | `/api/v1/rfqs` | `auth:sanctum` | List RFQs (scoped: Superadmin all, buyers own only); optional `status` filter, `per_page` pagination |
| POST | `/api/v1/rfqs` | `auth:sanctum` | Create RFQ with nested items (transactional) |
| GET | `/api/v1/rfqs/{rfq}` | `auth:sanctum` | Show single RFQ with eager-loaded items and products |
| POST | `/api/v1/rfqs/{rfq}/respond` | `auth:sanctum` + `RfqPolicy@respond` | Superadmin-only: submit quoted prices and validity |
| PATCH | `/api/v1/rfqs/{rfq}/status` | `auth:sanctum` + `RfqPolicy@updateStatus` | Owner or Superadmin: accept / reject / cancel with transition validation |

### Filtering Parameters (`GET /api/v1/rfqs`)
| Parameter | Type | Description |
|-----------|------|-------------|
| `status` | string | Filter by RfqStatus (`SUBMITTED`, `QUOTED`, `APPROVED`, `REJECTED`, `CANCELLED`, etc.) |
| `per_page` | integer | Pagination size (default 15) |
| `page` | integer | Page number |

### Status Transition Matrix
| From | To | Allowed By |
|------|----|------------|
| `SUBMITTED` | `CANCELLED` | Owner or Superadmin |
| `QUOTED` | `APPROVED` | Owner or Superadmin |
| `QUOTED` | `REJECTED` | Owner or Superadmin |
| `QUOTED` | `CANCELLED` | Owner or Superadmin |
| Any | Any | Superadmin (unrestricted) |

Invalid transitions return **422 Unprocessable Entity** with `{ success: false, message: "Transisi status tidak valid.", errors: { status: [...] } }`.

### RBAC Policy (`RfqPolicy`)
- `viewAny` → **true** (controller scopes the query)
- `view` → **owner OR Superadmin**
- `create` → **buyer (BUYER_B2B/BUYER_B2G) or Superadmin**
- `respond` → **Superadmin only**
- `updateStatus` → **owner OR Superadmin** (controller validates transitions)

### Key Files Created/Modified
- `app/Enums/RfqStatus.php` — added `QUOTED` and `CANCELLED`
- `app/Models/Rfq.php` — added `valid_until`, `admin_notes`, `status` to `$fillable`; datetime cast for `valid_until`
- `app/Models/RfqItem.php` — added `target_price`, `notes` to `$fillable`; decimal casts
- `database/migrations/2026_08_13_add_rfq_workflow_columns.php` — adds `valid_until`/`admin_notes` to `rfqs`, `target_price`/`notes` to `rfq_items`
- `app/Http/Requests/Rfq/StoreRfqRequest.php` — nested items validation (product_id, quantity, target_price)
- `app/Http/Requests/Rfq/RespondRfqRequest.php` — Superadmin-only; offered_price, valid_until (after today)
- `app/Http/Requests/Rfq/UpdateRfqStatusRequest.php` — status validation against all enum values
- `app/Http/Resources/Rfq/RfqItemResource.php` — item serialization with subtotal calculations
- `app/Http/Resources/Rfq/RfqResource.php` — RFQ serialization with total computations and status labels
- `app/Policies/RfqPolicy.php` — RBAC for RFQ operations
- `app/Http/Controllers/Api/Rfq/RfqController.php` — full CRUD + respond + status update with transition validation
- `routes/api.php` — RFQ routes under `/api/v1/rfqs`
- `tests/Feature/Api/RfqTest.php` — 12 tests (isolation, Superadmin respond, owner transitions, 403/422 enforcement)

---

## Module 4 — Order & Invoicing Workflow API

### Implemented Routes & Methods
| Method | Endpoint | Guard | Description |
|--------|----------|-------|-------------|
| GET | `/api/v1/orders` | `auth:sanctum` | List orders (scoped: Superadmin all, buyers own only); optional `status` filter, `per_page` pagination |
| POST | `/api/v1/orders` | `auth:sanctum` + `OrderPolicy@create` | Convert an approved RFQ into an order (transactional) |
| GET | `/api/v1/orders/{order}` | `auth:sanctum` + `OrderPolicy@view` | Show order with items, RFQ, BAST, and invoices |
| PATCH | `/api/v1/orders/{order}/status` | `auth:sanctum` + `OrderPolicy@updateStatus` | Advance / cancel order with transition validation; auto-generates BAST on `DELIVERED` |
| GET | `/api/v1/orders/{order}/bast` | `auth:sanctum` + `BastDocumentPolicy@view` | Show the order's BAST document |
| POST | `/api/v1/orders/{order}/bast/sign` | `auth:sanctum` + `BastDocumentPolicy@sign` | Buyer signs BAST → order `COMPLETED`, auto-generates UNPAID invoice |
| GET | `/api/v1/invoices` | `auth:sanctum` | List invoices (scoped by role); optional `payment_status` filter |
| GET | `/api/v1/invoices/{invoice}` | `auth:sanctum` + `InvoicePolicy@view` | Show single invoice |
| PATCH | `/api/v1/invoices/{invoice}/payment-status` | `auth:sanctum` + `InvoicePolicy@updatePaymentStatus` | Superadmin-only: mark invoice UNPAID/PAID/OVERDUE (sets `paid_at` on PAID) |

### Order Statuses (`OrderStatus`)
Replaced with the procurement lifecycle set: `PENDING_PAYMENT`, `PROCESSING`, `SHIPPED`, `DELIVERED`, `COMPLETED`, `CANCELLED`.

### Status Transition Matrix
| From | To | Allowed By |
|------|----|------------|
| `PENDING_PAYMENT` | `PROCESSING`, `CANCELLED` | Forward: Superadmin; cancel: Owner or Superadmin |
| `PROCESSING` | `SHIPPED`, `CANCELLED` | Forward: Superadmin; cancel: Owner or Superadmin |
| `SHIPPED` | `DELIVERED`, `CANCELLED` | Forward: Superadmin; cancel: Owner or Superadmin |
| `DELIVERED` | `COMPLETED`, `CANCELLED` | Forward: Superadmin; cancel: Owner or Superadmin |
| `COMPLETED` | — | Terminal |
| `CANCELLED` | — | Terminal |

Side effects:
- **`DELIVERED`** → auto-creates a `BastDocument` (`PENDING_SIGNATURE`) if none exists.
- **BAST sign** (`POST /orders/{order}/bast/sign`) → BAST `SIGNED` (records `signed_by`, `signed_at`, `signed_date`, `notes`), order `COMPLETED`, and generates an `Invoice` (`UNPAID`) with `subtotal` (order total), `tax_amount` (per-item product tax rate), `grand_total`, and `amount_due`, due `top_days` after issue.

### RFQ → Order Conversion
`POST /api/v1/orders` with `{ rfq_id }` requires the RFQ to be `APPROVED` and not yet converted. The order is created as `PENDING_PAYMENT` for the RFQ owner and its items are copied at the quoted `negotiated_price`; the RFQ is then set to `CONVERTED_TO_ORDER`. Violations return 403/422.

### Invoice Payment Status (`InvoiceStatus`)
`UNPAID`, `OVERDUE`, `PAID` (stored in `invoices.status`, exposed as `payment_status` in the resource). Only Superadmin may update it; `PAID` stamps `paid_at`.

### RBAC Policies
- **OrderPolicy** — `viewAny` true (scoped in controller), `view` owner/Superadmin, `create` buyer/Superadmin, `updateStatus` owner/Superadmin (controller validates transitions)
- **BastDocumentPolicy** — `view` owner/Superadmin, `sign` owner/Superadmin
- **InvoicePolicy** — `view` owner/Superadmin, `updatePaymentStatus` Superadmin only

### Key Files Created/Modified
- `app/Enums/OrderStatus.php` — replaced with the new lifecycle statuses
- `app/Enums/BastStatus.php` — new (`PENDING_SIGNATURE`, `SIGNED`)
- `database/migrations/2026_08_14_add_order_workflow_columns.php` — MySQL-guarded `orders.status` enum ALTER; `bast_documents` gains `status`, `signed_by`, `signed_at`, `notes` and nullable `bast_document_url`/`signed_date`; `invoices` gains `subtotal`, `tax_amount`, `grand_total`
- `app/Models/Order.php` — `statusLabel()` helper
- `app/Models/BastDocument.php` — BAST signing fields + `BastStatus` cast + `statusLabel()`
- `app/Models/Invoice.php` — `subtotal`/`tax_amount`/`grand_total` fillable + casts
- `app/Http/Requests/Order/CreateOrderFromRfqRequest.php`, `UpdateOrderStatusRequest.php`
- `app/Http/Requests/Bast/SignBastRequest.php`
- `app/Http/Requests/Invoice/UpdateInvoicePaymentStatusRequest.php`
- `app/Http/Resources/Order/OrderResource.php`, `OrderItemResource.php`, `app/Http/Resources/Bast/BastResource.php`, `app/Http/Resources/Invoice/InvoiceResource.php`
- `app/Policies/OrderPolicy.php`, `BastDocumentPolicy.php`, `InvoicePolicy.php`
- `app/Http/Controllers/Api/Order/OrderController.php`, `Api/Bast/BastController.php`, `Api/Invoice/InvoiceController.php`
- `routes/api.php` — order, BAST, and invoice routes
- `tests/Feature/Api/OrderTest.php` — 10 tests (conversion, isolation, status transitions, BAST generation)
- `tests/Feature/Api/InvoiceTest.php` — 8 tests (BAST signing → invoice, payment status updates, RBAC)

---

## Audit — Security, Concurrency & Precision Fixes

### Concurrency (TOCTOU)
- `OrderController::store` now calls `$rfq->lockForUpdate()` **inside** the transaction, then re-checks `Order::where('rfq_id', $rfq->id)->exists()` before creating the order. A `QueryException` with SQLSTATE `23000` (unique-violation) is caught and returned as HTTP 422.
- `orders.rfq_id` retains its `UNIQUE` index (migration `2026_08_12_060604`) — the DB is the final safety net.

### RFQ Item Ownership
- `RfqController::respond` now validates that every submitted `rfq_item_id` belongs to the RFQ being responded to; a mismatch returns 422 (`items.*.rfq_item_id`) with **no partial writes**.

### Authorization
- `OrderPolicy::create(User $user, Rfq $rfq)` checks `$user->is($rfq->user)` or Superadmin; the controller passes the RFQ instance.

### Monetary Precision
- All tax / total calculations in `BastController::generateInvoice()` use BC Math (`bcadd`, `bcmul`, `bcdiv`) with 2-decimal scale; per-item tax = `subtotal × tax_rate ÷ 100`, summed across items.

### New Tests Added
| Test | File | Purpose |
|------|------|---------|
| `test_rfq_can_only_be_converted_to_one_order` | OrderTest | Second conversion → 422, exactly 1 order in DB |
| `test_concurrent_conversion_is_prevented_by_db_unique_constraint` | OrderTest | Direct `INSERT` with same `rfq_id` → QueryException (skipped on SQLite) |
| `test_database_prevents_duplicate_rfq_conversion` | OrderTest | Same as above, separate test (skipped on SQLite) |
| `test_rfq_item_belonging_to_another_rfq_is_rejected` | RfqTest | Foreign `rfq_item_id` → 422, no DB writes |
| `test_valid_rfq_items_still_work_after_fix` | RfqTest | Regression guard for valid respond flow |

### Files Changed (commit `977148e`)
`OrderController`, `OrderPolicy`, `RfqController`, `OrderTest`, `RfqTest`

### Verified
`pint` ✅ · `php artisan test` ✅ 49 passed / 3 skipped (SQLite concurrency skips) · 239 assertions

---

## Module 5 — Payment Processing & Payment Terms (TOP / Tax Handling) API

### Implemented Routes & Methods
| Method | Endpoint | Guard | Description |
|--------|----------|-------|-------------|
| POST | `/api/v1/invoices/{invoice}/payments` | `auth:sanctum` | Buyer submits a payment proof (amount, method, date, file) → `PENDING_VERIFICATION` |
| GET | `/api/v1/payments` | `auth:sanctum` | List payments; Superadmin sees all, buyers only their own invoices; `status` filter |
| PATCH | `/api/v1/payments/{payment}/verify` | `auth:sanctum` | Superadmin approves (`VERIFIED`) or rejects (`REJECTED`) a payment; auto-reconciles invoice |

### Payment Terms (`PaymentTerm`)
- Enum `IMMEDIATE`(0), `TOP_14`(14), `TOP_30`(30), `TOP_60`(60) with `days()` and `statusLabel()`.
- Stored as a snapshot on `invoices.payment_term` (default `TOP_30`); `due_date` is computed at issuance as `issued_date + payment_term.days()`.
- `BastController::generateInvoice()` maps the order's `top_days` (0/14/30/60) to the enum via `resolvePaymentTerm()`.

### Tax Breakdown (`InvoiceResource`)
- `tax_amount` column renamed to **`ppn_amount`**; new `pph_amount` + `payment_term` columns on `invoices`; per-product `products.pph_rate_percentage` (nullable).
- PPN and PPh computed **per item** with BC Math: `subtotal × rate ÷ 100`, summed, 2-decimal scale.
- **PPh is a withholding, NOT added to the billed amount**: `grand_total = subtotal + ppn`; `amount_due = grand_total`. PPh is recorded separately for reporting.

### Payment Status State Machine (`PaymentStatus`)
`PENDING_VERIFICATION` → `VERIFIED` | `REJECTED` (terminal). Only Superadmin may transition; already-settled payments return 422.
- Verification writes `verified_by`, `verified_at`; rejection additionally writes `rejection_reason` (required).

### Reconciliation Logic (`PaymentController::verify`)
Runs inside `DB::transaction()` with `lockForUpdate()` on both the payment and its invoice (serializes concurrent verifications):
- `Σ VERIFIED amounts ≥ grand_total` → invoice `PAID` + `paid_at = now()`
- `0 < Σ < grand_total` → `PARTIALLY_PAID`
- `Σ = 0` → `UNPAID`
- Summation uses `verifiedPaidAmount()` on the Invoice model via BC Math `bcadd`.

### RBAC Policies
- **PaymentPolicy** — `view` owner/Superadmin, `create` (invoice owner/Superadmin), `verify` Superadmin only
- **InvoicePolicy** — `view` owner/Superadmin, `updatePaymentStatus` Superadmin only (manual override kept)

### Key Files Created/Modified
- `database/migrations/2026_08_14_add_payment_processing.php` — renames `invoices.tax_amount→ppn_amount`, adds `pph_amount`/`payment_term`, adds `products.pph_rate_percentage`, creates `payments`
- `app/Enums/PaymentTerm.php`, `PaymentStatus.php`, `PaymentMethod.php` — new; `InvoiceStatus` gains `PARTIALLY_PAID`
- `app/Models/Payment.php` — new; `Invoice` gains `payments()` + `verifiedPaidAmount()`; `Product` gains `pph_rate_percentage`
- `app/Http/Requests/Payment/SubmitPaymentRequest.php`, `VerifyPaymentRequest.php` — new
- `app/Http/Resources/Payment/PaymentResource.php` — new; `InvoiceResource` exposes `ppn_amount`, `pph_amount`, `payment_term`, `paid_amount`, `payments`
- `app/Policies/PaymentPolicy.php` — new
- `app/Http/Controllers/Api/Payment/PaymentController.php` — new (`store`, `index`, `verify`)
- `app/Http/Controllers/Api/Bast/BastController.php` — invoice generation reworked for PPN/PPh/payment term
- `routes/api.php` — payment routes
- `database/factories/PaymentFactory.php` — new; `InvoiceFactory`/`ProductFactory` updated
- `tests/Feature/Api/PaymentTest.php` — 14 tests; `InvoiceTest` updated for rename + PPh test

### Verified
`pint` ✅ · `php artisan test` ✅ 64 passed / 3 skipped (SQLite concurrency skips) · 384 assertions

---

## Module 6 — Notification System (In-App & Email) API

### Implemented Routes & Methods
| Method | Endpoint | Guard | Description |
|--------|----------|-------|-------------|
| GET | `/api/v1/notifications` | `auth:sanctum` | Paginated, newest-first list of the caller's notifications |
| PATCH | `/api/v1/notifications/{notification}/read` | `auth:sanctum` | Mark a single notification as read (recipient only) |
| POST | `/api/v1/notifications/read-all` | `auth:sanctum` | Mark all of the caller's unread notifications as read |

### Notification Classes (queued, `database` + `mail` channels)
- `RfqSubmittedNotification` — sent to **all** `SUPERADMIN` users when a buyer submits an RFQ.
- `RfqRespondedNotification` — sent to the **RFQ owner** when a Superadmin responds with a quote.
- `OrderShippedNotification` — sent to the **buyer** when an order transitions to `SHIPPED`.
- `PaymentVerifiedNotification` — sent to the **buyer** when a payment is verified, carrying the reconciled invoice status (`PAID` / `PARTIALLY_PAID`).

All notifications implement `ShouldQueue`; `toArray()` returns structured data (`title`, `message`, `action_url`, entity id/number, `invoice_status` for payments); `toMail()` returns an Indonesian `MailMessage`. Triggers fire **after** the surrounding `DB::transaction` commits.

### Delivery & Storage
- `notifications` table: `uuid` PK (via `uuidMorphs('notifiable')`, matching the UUID `users.id`), `type`, text `data`, nullable `read_at`, timestamps.
- `App\Models\Notification` extends `DatabaseNotification` + `HasUuids`; adds `isRead()` helper.
- Production/dev use `QUEUE_CONNECTION=database`, `MAIL_MAILER=log` → run `php artisan queue:work`. Tests use `QUEUE_CONNECTION=sync` + `MAIL_MAILER=array` so notifications run inline.

### API Response
- `NotificationResource` fields: `id`, `type` (class basename), `title`, `message`, `action_url`, `read_at` (ISO), `is_read`, `created_at`.
- List endpoint wraps results in `data.items` (flat array) + `data.pagination` (`current_page`, `per_page`, `total`, `last_page`).

### RBAC Policies
- **NotificationPolicy** — `view` SUPERADMIN or recipient; `markAsRead` (update) recipient only. A non-recipient `PATCH` returns 403.

### Key Files Created/Modified
- `database/migrations/2026_08_14_create_notifications_table.php` — schema
- `app/Models/Notification.php` — new
- `app/Policies/NotificationPolicy.php` — new
- `app/Notifications/RfqSubmittedNotification.php`, `RfqRespondedNotification.php`, `OrderShippedNotification.php`, `PaymentVerifiedNotification.php` — new
- `app/Http/Resources/Notification/NotificationResource.php` — new
- `app/Http/Controllers/Api/Notification/NotificationController.php` — new (`index`, `markAsRead`, `readAll`)
- `routes/api.php` — notification routes
- `app/Http/Controllers/Api/Rfq/RfqController.php`, `Api/Order/OrderController.php`, `Api/Payment/PaymentController.php` — notification dispatch triggers
- `tests/Feature/Api/NotificationTest.php` — 8 tests; `RfqTest`/`OrderTest`/`PaymentTest` extended with `Notification::fake()` + `assertSentTo`

### Verified
`pint` ✅ · `php artisan test` ✅ 72 passed / 3 skipped (SQLite concurrency skips) · 453 assertions
---

## Module 7 - Document Engine (PDF Generation)

### Implemented Routes & Methods
| Method | Endpoint | Guard | Description |
|--------|----------|-------|-------------|
| GET | `/api/v1/rfqs/{rfq}/quotation.pdf` | `auth:sanctum` + `RfqPolicy@view` | Stream the Surat Penawaran Harga (quotation) PDF as `application/pdf` (inline) |
| GET | `/api/v1/orders/{order}/bast.pdf` | `auth:sanctum` + `BastDocumentPolicy@view` | Stream the BAST draft/signed PDF |
| GET | `/api/v1/invoices/{invoice}/pdf` | `auth:sanctum` + `InvoicePolicy@view` | Stream the invoice PDF |

Missing or ungenerated documents return **404** with `{ success:false, message:"Dokumen belum tersedia." }`.

### PDF Generation Strategy
- **Auto-generated on lifecycle events** (not on demand): quotation on `RfqController::respond`, BAST draft when order transitions to `SHIPPED`, invoice when the BAST is signed.
- Library: `barryvdh/laravel-dompdf` (v3.1.2, pure PHP) rendering Blade templates under `resources/views/pdf/`.
- Files stored on a private local **`documents`** disk (`storage/app/private/documents`) via `app/Services/PdfService.php::generate()`; a failure is caught + `report()`ed and the business action still succeeds with the stored URL left empty (download 404).
- Stored paths: `rfqs.quotation_pdf_url`, `bast_documents.bast_document_url`, `invoices.invoice_pdf_url` (all exposed by existing resources).

### Templates (`resources/views/pdf/`)
- `partials/styles.blade.php` - shared A4/dompdf-compatible CSS.
- `partials/kop-surat.blade.php` - letterhead (company identity + NIB/PKP/NPWP/address/contact + double rule).
- `rfq-quotation.blade.php` - bill-to block, item list (qty, offered unit price, totals), `valid_until`, notes.
- `bast.blade.php` - Pihak Pertama/Kedua blocks, item list, signature areas.
- `invoice.blade.php` - bill-to, item list, Subtotal/PPN/PPh/grand total summary, bank transfer details from `config('company.bank')`, e-Faktur number placeholder.
- Indonesian dates via `app()->setLocale('id')` + `\Illuminate\Support\Carbon::setLocale('id')` + `->translatedFormat('j F Y')`; money formatted `1.234.567,89` via a `$money` helper.

### Company Letterhead Config (`config/company.php`)
All keys are env-driven with sensible placeholders: `name`, `legal_entity`, `nib`, `pkp`, `npwp`, `address`, `phone`, `email`, `website`, and `bank` (`name`, `account_name`, `account_number`, `branch`). Edit the `.env` `COMPANY_*` values before production use.

### Key Files Created/Modified
- `composer.json` / `composer.lock` - added `barryvdh/laravel-dompdf`
- `config/filesystems.php` - added `documents` disk
- `config/company.php` - new letterhead config
- `app/Services/PdfService.php` - new PDF render + store service
- `app/Http/Controllers/Api/Document/DocumentController.php` - new download endpoints
- `resources/views/pdf/partials/styles.blade.php`, `partials/kop-surat.blade.php`, `rfq-quotation.blade.php`, `bast.blade.php`, `invoice.blade.php` - templates
- `app/Http/Controllers/Api/Rfq/RfqController.php` - quotation PDF hook in `respond`
- `app/Http/Controllers/Api/Order/OrderController.php` - BAST creation + draft PDF moved from `DELIVERED` to `SHIPPED`
- `app/Http/Controllers/Api/Bast/BastController.php` - invoice PDF generation in `generateInvoice()`; removed placeholder URLs
- `routes/api.php` - 3 document download routes
- `tests/Feature/Api/DocumentTest.php` - 10 tests (generation + storage, download 200/404/401/403, config completeness)

### Behavior Note
`BastDocument` records are now created when the order reaches `SHIPPED` (per the procurement state machine `PROCESSING -> SHIPPED -> BAST_SIGNED`), not `DELIVERED`. Signing still requires `DELIVERED` (existing guard unchanged).

### Verified
`pint` OK, `php artisan test` OK: 82 passed / 3 skipped (SQLite concurrency skips) / 506 assertions

## Module 8 - Audit Trail, Overdue Invoice Scheduler & Executive Analytics API

**Status: COMPLETE**

### Audit Trail Subsystem
- `app/Services/AuditLogger.php` - failure-tolerant logger (`log()` + `snapshot()`) with a per-entity state field map; writes after the business transaction and `report()`s failures so auditing never aborts an action. System events pass `user_id => null`.
- `app/Enums/AuditAction.php` - string enum of audited actions: `RFQ_CREATED`, `RFQ_QUOTED`, `RFQ_STATUS_UPDATED`, `ORDER_CREATED`, `ORDER_STATUS_UPDATED`, `BAST_CREATED`, `BAST_SIGNED`, `INVOICE_CREATED`, `INVOICE_STATUS_UPDATED`, `INVOICE_MARKED_OVERDUE`, `PAYMENT_SUBMITTED`, `PAYMENT_VERIFIED`, `PAYMENT_REJECTED`.
- Hooks (post-transaction): `RfqController` (store/respond/updateStatus), `OrderController` (store/updateStatus incl. `BAST_CREATED` on SHIPPED), `BastController::sign` (`BAST_SIGNED` + `ORDER_STATUS_UPDATED` + `INVOICE_CREATED`), `InvoiceController::updatePaymentStatus`, `PaymentController` (store/verify incl. `INVOICE_STATUS_UPDATED` on reconciliation).
- Schema: existing `audit_logs` table (uuid id, nullable `user_id`, `action`, `entity_type`, `entity_id`, `previous_state`/`new_state` JSON, `created_at`) + `AuditLog` model + `User::auditLogs()`.
- `AuditLogController::index` - `GET /api/v1/audit-logs` (Superadmin only via `AuditLogPolicy@viewAny`), filters `entity_type` + `action` (invalid action => 422), `per_page`, `latest()` first, includes `user`.
- `app/Http/Resources/AuditLog/AuditLogResource.php` - exposes user, action, entity, states, created_at.

### Overdue Invoice Scheduler
- `app/Console/Commands/CheckOverdueInvoices.php` - `php artisan invoices:check-overdue`: selects `UNPAID`/`PARTIALLY_PAID` invoices with `due_date < today`, flips them to `OVERDUE` in a single transaction, writes one `INVOICE_MARKED_OVERDUE` audit row per invoice (`user_id => null`), prints the affected count.
- Registered daily via `->withSchedule(...)` in `bootstrap/app.php` (Laravel 13 idiom).

### Executive Analytics API
- `GET /api/v1/analytics/dashboard` - Superadmin only via `AnalyticsPolicy@view` (explicitly registered in `AppServiceProvider` since it has no backing model). Returns:
  - `rfqs.by_status` + total
  - `orders.by_status` (count + total value per status) + total count/value
  - `outstanding_receivables.total` + per-status breakdown (UNPAID / PARTIALLY_PAID / OVERDUE sums of `amount_due`)
  - `verified_payments.total_amount` + count (VERIFIED)
  - `tkdn_compliance.average_tkdn_percentage` - quantity-weighted `SUM(tkdn_percentage * qty) / SUM(qty)`
  - `generated_at`

### Routes
| Method | URI | Guard | Purpose |
| GET | `/api/v1/audit-logs` | `auth:sanctum` + `AuditLogPolicy@viewAny` | Paginated audit trail, filters `entity_type`/`action` |
| GET | `/api/v1/analytics/dashboard` | `auth:sanctum` + `AnalyticsPolicy@view` | Executive metrics dashboard |

### Key Files Created/Modified
- `app/Enums/AuditAction.php`, `app/Services/AuditLogger.php` - new
- `app/Http/Controllers/Api/AuditLog/AuditLogController.php`, `app/Http/Resources/AuditLog/AuditLogResource.php`, `app/Policies/AuditLogPolicy.php` - new
- `app/Http/Controllers/Api/Analytics/AnalyticsController.php`, `app/Policies/AnalyticsPolicy.php` - new
- `app/Console/Commands/CheckOverdueInvoices.php` - new
- `bootstrap/app.php` - `->withSchedule(...)` daily run
- `app/Providers/AppServiceProvider.php` - explicit `Gate::policy` registration for AnalyticsPolicy
- `app/Http/Controllers/Api/{Rfq,Order,Bast,Invoice,Payment}/*` - audit hooks
- `routes/api.php` - audit-logs + analytics routes
- `tests/Feature/Api/Module8Test.php` - 14 tests

### Verified
`pint` OK, `php artisan test` OK: 96 passed / 3 skipped (SQLite) / 599 assertions