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

> **Phase A (AUTH-1):** `ProductController::destroy` now calls `$this->authorize('delete', $product)`; a non-superadmin `DELETE /products/{product}` returns **403** (previously 200). Covered by `test_non_admin_receives_403_on_product_delete`.

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
- `tests/Feature/Api/InvoiceTest.php` — 13 tests (BAST signing → invoice, single-invoice invariant, payment status updates, RBAC)

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
| GET | `/api/v1/payments/{payment}/proof` | `auth:sanctum` | Stream the payment proof file (owner/Superadmin only, 404 if missing) |

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
- `tests/Feature/Api/PaymentTest.php` — 20 tests; `InvoiceTest` updated for rename + PPh test

> **Phase A (SEC-1):** payment proofs are stored on the private `documents` disk (`payments/proofs/...`); `proof_file_url` holds the private path (no public URL). A guarded `GET /payments/{payment}/proof` streams the file with the correct Content-Type; 404 when the file is missing; 403 for non-owners.

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

---

## Phase B - Foundation Hardening & Reliability Remediation

**Status: COMPLETE — GATE: PASS**

Resolves the approved P2/P3 findings deferred from Phase A: AUTH-2, SEC-2, AUD-1, SCHED-1, PERF-1, product search (QRY-1), unbounded pagination (VAL-1), nullable product description, factory correctness, identifier collision retry loops, and two broken migration `down()` methods. All fixes follow REPRODUCE → FIX → TARGETED TEST → REGRESSION → FULL VERIFICATION.

### Findings Fixed
| ID | Severity | Finding | Fix |
|----|----------|---------|-----|
| AUTH-2 | High | No login rate limit | `RateLimiter::for('login')` in `AppServiceProvider::boot()` (5/min per email+IP, 429 JSON envelope, `LOGIN_THROTTLED` audit); AuthTest covers throttle/per-email/window-reset |
| SEC-2 | High | `password123` seeder, no production-safe path | Fail-closed `UserSeeder` (`SEED_DEMO_USERS` + named env passwords required in prod), config `app.demo` block, `.env.example` keys, `UserSeederTest` (4) |
| AUD-1 | Medium | Audit gaps (auth/product/profile/throttle) | New `AuditAction` cases + nullable `entity_type/entity_id` (migration `2026_08_13_091233`); wired into Auth/Profile/Product controllers; `AuditLogTest` (7) |
| SCHED-1 | Medium | Overdue command: single large transaction, no locks | `lockForUpdate()` inside the transaction + `->withoutOverlapping()`; idempotency test in Module8Test |
| PERF-1 | Medium | Missing `audit_logs` indexes | New indexes (migration `2026_08_13_091945`); EXPLAIN-verified (backward index scans) on MySQL 8.4.3 |
| QRY-1 | Medium | Product search `%term` + ungrouped `orWhere` | `%term%` inside a grouped `where(...)` closure; regression tests |
| VAL-1 | Medium | Unbounded `per_page` | `Controller::perPage()` clamps 1..100 (default 15) at all 7 listing controllers; regression test |
| P3 | Low | `products.description` required at DB level | Nullable (Option B) via migration `2026_08_13_110000`; requests already nullable |
| P3 | Low | Factory gaps (`PaymentFactory::verified` `verified_by`; `ProductFactory` slug) | Fixed both factories |
| P3 | Low | `Str::random(10)` identifier collisions | New `App\Services\UniqueIdentifier` retry-loop helper; ORD-/BAST-/INV-/RFQ- wired |
| P3 | Low | Broken `down()` on 2 MySQL migrations | Fixed (FK-first unique-index drop; widen/remap/narrow enum); verified with real data |

### Verification Evidence (real command output)
- **SQLite suite:** `146 tests / 144 passed / 2 pre-existing skips / 856 assertions / ~38s`.
- **MySQL 8.4.3 suite (throwaway DB):** `146 / 146 passed / 860 assertions / 0 skipped / ~46s`.
- **Migration lifecycle (MySQL 8.4.3):** `migrate:fresh` (22 migrations) + full `migrate:rollback` clean with data present.
- `pint --test` ✅ · `composer audit` ✅ · `route:list` ✅.

### Gate Decision
**PASS** — all Phase B findings are implemented with targeted tests, the suite is genuinely green on both sqlite and MySQL 8.4 with real command evidence, migrations are reversible with data, and static gates are clean. Full report in `docs/PHASE_B_REMEDIATION_REPORT.md`.

---

## Phase A - Audit Remediation (Security, Data Integrity & Test Discovery)

**Status: COMPLETE — GATE: PASS**

Resolves the five confirmed findings from the earlier code audit (AUTH-1, SEC-1, INT-1, INT-2, TEST-1) plus three latent MySQL-only defects surfaced while proving the required MySQL verification. All fixes follow TDD (RED → GREEN), preserve business behavior, and are covered by tests.

### Findings Fixed
| ID | Severity | Finding | Fix |
|----|----------|---------|-----|
| TEST-1 | High | `ProductTest` (9 tests) silently skipped: PHPUnit 12 dropped `/** @test */`, so the "green suite" was a false green | Rewrote `ProductTest` with `test_` prefixes; now fully discovered & passing |
| AUTH-1 | High | `DELETE /api/v1/products/{product}` by a non-superadmin returned **200** (policy never enforced) | `ProductController::destroy` calls `$this->authorize('delete', $product)` → 403; test added |
| SEC-1 | Medium | Payment proofs were stored with a guessable public path in `storage/app/public` and the URL returned in `proof_file_url` | Stored on the private `documents` disk; `proof_file_url` holds the private path; added guarded `GET /payments/{payment}/proof` (200/401/403/404) |
| INT-1 | Medium | Concurrent BAST signing could create duplicate invoices per order | New unique index `invoices_order_unique` on `invoices.order_id` (migration `2026_08_15_add_unique_invoice_per_order.php`) + `lockForUpdate()` on the order row in `BastController::sign`; tests: re-sign → 422 / exactly one invoice / DB rejects duplicates |
| INT-2 | Medium | `orders.top_days` CHECK (`> 0`) silently rejected `IMMEDIATE` (0-day) payment terms at the DB level | Relaxed to `>= 0` on MySQL/PostgreSQL (migration `2026_08_15_relax_orders_top_days_check.php`); tests: top_days=0 persists, IMMEDIATE sets due_date = issued_date |
| INT-3* | High | `orders.rfq_id` UNIQUE index declared in the create migration is **never emitted** by Laravel when `constrained()` is chained → one-order-per-RFQ invariant unenforced at the DB level on MySQL | New migration `2026_08_15_add_unique_index_on_orders_rfq_id.php` (idempotent, both engines); MySQL direct-insert + doctrine tests now pass |
| * | Medium | `test_orders_table_has_unique_index_on_rfq_id` errored on MySQL: `getDoctrineSchemaManager` removed in Laravel 13 | Rewritten with `Schema::getIndexes()` (runs on sqlite AND MySQL, skip removed) |
| * | Medium | Overdue-invoice test data set `due_date < issued_date`, violating `invoices_due_after_issued` on MySQL | Test now backdates `issued_date` 40 days; command + assertions unchanged |

\* Discovered during the mandated MySQL 8.4 verification; fixed because they block a clean MySQL run and are the same integrity class the audit targets.

### Verification Evidence (real command output)
- **SQLite suite (default phpunit.xml):** `vendor/bin/phpunit` → **118 tests / 116 passed / 2 skipped / 690 assertions / ~21s**. The 2 skips are the pre-existing SQLite-only concurrency tests (`OrderTest:297`, `OrderTest:358`); `OrderTest:340` previously skipped now runs on sqlite too.
- **MySQL 8.4.3 suite (throwaway `pengadaan_barang_ajm_test` DB):** **118 tests / 118 passed / 694 assertions / ~22s — 0 skipped** (the SQLite-only tests execute and pass on MySQL).
- `php artisan migrate:fresh --seed` ✅ on MySQL 8.4.3 (all 18 migrations incl. the 3 new ones).
- Direct MySQL checks ✅: `invoices_order_unique` present and enforced (duplicate → 1062), `orders_top_days_non_negative (top_days >= 0)` replaces `orders_top_days_positive`, `orders_rfq_id_unique` present and enforced.
- `vendor/bin/pint --test` ✅ on all touched files.
- `php artisan route:list` ✅ shows `api/v1/payments/{payment}/proof`.
- `composer audit` ✅ no advisories.

### Remaining Findings (deferred, not fixed in Phase A)
See the report for the full P2/P3 inventory (query indexing QRY-1, validation VAL-1, stock INT, overpayment cap, float `(float)` cast, lax status transitions, tax-rate snapshots, AUTH-2 login throttle/token expiry, SEC-2 seeder password, AUD-1 audit gaps, scheduler transaction size, missing indexes, .env defaults; P3 items). These are intentionally left for Phase B / Module 9 to avoid scope creep.

### Gate Decision
**PASS** — all Phase A fixes are implemented, the previously-false-green suite is now genuinely green on both sqlite and MySQL 8.4 with real command evidence, migrations are reversible, no data-destroying operations, and business behavior is preserved. Module 9 / Phase B may proceed.

---

## Phase 3C.5 — Public Catalog Discovery (Search / Filter / Sort)

**Status: GATE A COMPLETE — APPROVED 2026-09-20. Backend discovery core implemented and fully verified (SQLite + MySQL 8.4, Pint, diff check, initial-HTML SEO delivery). GATE B (frontend toolbar) awaits separate authorization.**

Master plan: `docs/superpowers/plans/2026-09-01-3c5-catalog-discovery.md`

### Implemented (GATE A)
- **Search** (`?q=` — partial, case-insensitive on `title` OR `sku`; description intentionally NOT searchable)
- **Filter** (`?sni=1`, `?tkdn_min=<0-100 clamped>`, `?availability=1` — stock > 0)
- **Sort** (strict allowlist via `App\Enums\CatalogSort`: `default`, `price_asc`, `price_desc`, `tkdn_desc`, `newest`; every non-default sort carries an `id` tie-break; `tkdn_desc` puts NULL last, known values incl. 0 before NULL, via `(tkdn_percentage IS NULL), tkdn_percentage DESC`)
- URL is the source of truth; Back/Forward supported; discovery change resets `page` to 1; invalid/garbage/array-flooded params are ignored (never 500).
- `pagination.baseUrl` preserves discovery params (page excluded); `hasActiveDiscovery` true on any discovery-active state including `page>1`.
- Public payload allowlist remains **exactly** `{id, sku, title, description, price, tkdn, hasSni, warrantyInfo, available}`; raw `stock` never serialized; `/api/v1/products` + `ProductResource` untouched.
- **SEO delivery:** initial HTML carries `<meta name="robots" content="noindex,follow">` for discovery-active URLs; production emits canonical + `og:url` → clean `/katalog` exactly once; clean `/katalog` indexable. Server-rendered in `app.blade.php` (no SSR).

### Key Files Created/Modified
- `app/Enums/CatalogSort.php` (new), `app/Models/Product.php` (scopes), `app/Http/Controllers/CatalogController.php` (`normalizeDiscovery()`, `paginationBaseUrl()`, `hasActiveDiscovery`, SEO props)
- `resources/views/app.blade.php` (server-side robots/canonical/og:url), `resources/js/Pages/Catalog.vue` (props kept; client-side SEO tags removed — single server-side source of truth)
- `tests/Unit/CatalogSortTest.php` (8), `tests/Feature/Web/CatalogDiscoveryTest.php` (~35, incl. initial-HTML SEO delivery tests)

### Verification evidence (real output)
- SQLite suite ✅ **613 tests / 600 passed / 13 skipped / 2637 assertions**
- MySQL 8.4 suite ✅ **613 / 613 passed / 2674 assertions / 0 skipped**
- `pint --test` ✅ · `git diff --check` ✅ · raw initial-HTML SEO contract verified (robots/noindex + canonical/og:url exactly once, production-gated)
- EXPLAIN (dev DB, 13 rows): documentational — structure/plan correctness only; no index migration (per repo policy); migration candidates noted in plan §Query/performance.

### Explicit decisions (locked)
- **Category is DEFERRED BY DESIGN, not a missing implementation.** Not in 3C.5 scope; no schema/migration/model/UI.
- **Existing `/api/v1/products` contract must not be silently broken** — 3C.5 touches only the web `/katalog` path.
- No SSR; no speculative index migration; no middleware/service; product-detail (3C.6), Cara Order (3C.7), security hardening incl. the public API internal-field leak (3C.8) remain out of scope.

### GATE plan (remaining)
- **GATE B — Frontend:** discovery toolbar (search/filter/sort), `Pagination` param-preservation, active-summary/clear, EmptyState discovery copy, responsive + a11y. Not started.
- **GATE C — Integration:** E2E browser, EXPLAIN/query-shape verification, payload re-audit, SEO check, anti-slop/design pass, full SQLite + MySQL regression + Pint → verdict → checkpoint commit.

---

## Phase 3C.6 — Public Product Detail

**Status: COMPLETE — VALIDATED AND HUMAN-APPROVED 2026-10-01. Implementation locked; SQLite + MySQL 8.4 green; browser, accessibility, responsive and SEO validation passed; desktop visual review approved. COMMITTED as `205d1c8`; documentation status finalized in `6739077`; NOT PUSHED.**

Master plan: `docs/superpowers/plans/2026-10-01-3c6-product-detail.md`

### Planning completion
Planning, gate reconciliation and scope locking completed before implementation. The identifier, authorization boundary, public payload, case/canonical strategy, SEO guarantees and TKDN/SNI split were each locked explicitly; deviations required reporting back rather than unilateral change.

### Locked SKU route
- `GET /produk/{sku}` → `produk.show` → `App\Http\Controllers\ProductDetailController::show`.
- **Public identifier is the SKU** — not the slug, not the UUID. The SKU was already in the 3C.5 catalog payload allowlist, so the card became navigational with **no payload change and no slug exposure**.
- **Non-canonical SKU casing returns 404**, never a redirect. Verified engine-agnostically: `products.sku` is `utf8mb4_unicode_ci` (case-**insensitive**) on MySQL 8.4.3, so a naive `where('sku', …)` would wrongly return 200. Enforcement is the portable PHP check `$product->sku !== $sku` — no `COLLATE`, no `LOWER()`, no functional index, no collation migration.
- A genuinely **lowercase persisted SKU resolves** (`srv-0001` → `200`).
- **Authorization is public and locked:** no auth middleware, no `ProductPolicy` invocation, no new authorization framework. `ProductPolicy::view` is not guest-safe and was deliberately not used.
- Not-found is the **plain Laravel 404** — no custom 404 system was introduced.

### Six-field public projection
A dedicated inline projection; `ProductResource` is **not** reused. Exactly:

```
{ sku, title, description, price, warrantyInfo, available }
```

`price` is a string, `available` a boolean. `stock` is selected only to derive `available` and is then dropped, so the raw count never reaches the client. Excluded columns are never loaded, so they cannot be serialized: raw `stock`, `margin_percentage`, `tax_rate_percentage`, `pph_rate_percentage`, `estimated_shipping`, `slug`, `datasheet_url`, TKDN/SNI, `created_at`/`updated_at`, NIB/NPWP/PKP, bank details, private supplier data, compliance data, transaction data.

### TKDN/SNI exclusion
`tkdn_percentage` and `is_sni` are **excluded from Product Detail**. The 3C.5 catalog card's display-only TKDN/SNI presence markers are **untouched**. Reintroduction requires evidence-based justification and legal/regulatory review. **`ProductCertification` was NOT created.**

### Canonical / SEO contract (authoritative — no crawler-complete claim)
1. No `noindex` on Product Detail.
2. Exactly one canonical in production.
3. Exactly one matching `og:url` in production.
4. Canonical derived from the **persisted SKU** (`route('produk.show', ['sku' => $product->sku], absolute: false)` — the parameter is passed explicitly because implicit resolution would emit the UUID route key).
5. Non-production emits neither canonical nor `og:url` (gated on production env **and** `APP_PUBLIC_URL`).
6. **F2 inherited and NOT REMEDIATED** — Blade-level canonical/`og:url` stay tied to the initial shell across client-side Inertia navigation, consistent with the locked SSR-disabled architecture.

Guarantees 1–5 were verified against a real production-mode server serving the real initial document.

### Validation results (final baselines, not invented)
- SQLite suite: **649 total / 636 passed / 13 skipped / 0 failed** — 2877 assertions.
- MySQL 8.4.3 suite: **649 total / 649 passed / 0 failed** — 2914 assertions.
- Focused pre-regression suite (ProductDetail + Catalog + CatalogDiscovery): **84 tests / 779 assertions / 0 failed**.
- Focused Product Detail cross-engine subset on MySQL: **12/12 passed**.
- Delta from the 620-test baseline is **+29**, exactly matching the 29-test `ProductDetailTest`.
- `pint` passed · `git diff --check` clean · `npm run build` passed (563 modules; only the pre-existing optional `fontaine` warning).
- HTTP status matrix **13/13** with no redirects; production and non-production SEO shell both verified.

### Browser / E2E result
Catalog → detail → back passed with discovery state (`?sort=price_asc&page=2`) preserved and restored on Back. Keyboard-only navigation passed (Tab traversal from the skip link to the card link, then Enter). Skip link functional. Catalog card asserted in the live DOM as exactly one `<article>` + one `<a>` with no nested interactive elements. Mobile **375px** passed with **zero horizontal overflow**. Console clean.

One test was removed during validation: `test_catalog_emits_the_product_detail_link` asserted against server-rendered raw HTML for a link that only exists after Vue runtime rendering — invalid under the locked no-SSR architecture, not a broken feature. It was replaced by `test_catalog_payload_exposes_the_sku_needed_by_the_detail_link`. Browser/E2E is the authoritative navigation verification layer.

### Visual approval
The human reviewer **APPROVED** the supplied desktop Product Detail and desktop Catalog screenshots. Browser/mobile visual validation passed independently. **No visual redesign was required or performed.**

### Scope / out-of-scope summary
No API changes (`/api/v1/products`, `ProductResource`, API controllers/requests/policies untouched — the public API internal-field leak remains tracked as 3C.8); no migrations or schema changes; no `ProductResource` change; no `ProductPolicy` change; no `app.blade.php` change; no media subsystem; no publication state; no certification subsystem; no SSR; no Module 9 / dashboards / transaction workflows; no rate limiting; no custom 404 system; no shared `formatPrice` extraction; no SKU write-path validation change; no change to 3C.5 catalog behavior.

### Known accepted limitations
SKU mutability can orphan old URLs (no redirect map, SKU should be treated as effectively immutable); the `/` / reserved-character SKU limitation is inherited; SKU write validation remains pre-existing and out of scope; 404 remains plain Laravel; F2 remains inherited; the `fontaine` warning remains pre-existing.

### Teardown / hygiene
E2E fixtures lived only in a throwaway SQLite database on a dedicated port and were deleted. The MySQL **development** database was never written to (13 pre-existing rows, none created during 3C.6). `.env`, `config/database.php` and `resources/views/app.blade.php` unmodified. Servers stopped, ports released.## PHASE 3C.7 — Cara Order (Informational Page)

**Status:** FINALIZED / COMMITTED — NOT PUSHED
**Date:** 2026-10-03

### Objective
Public informational 'Cara Order' page explaining procurement-oriented B2B/B2G engagement; strictly informational with no transaction/RFQ flows.

### Implementation
- Route GET /cara-order named cara-order.show (closure, public, no middleware).
- Page
esources/js/Pages/CaraOrder.vue (static Inertia Vue) with B2B/B2G boundary-preserving content.
- Header: 'Cara Order' linked to /cara-order; 'Produk'/'Kontak' unchanged.
- Tests: 	ests/Feature/Web/CaraOrderTest.php (3 passed, 9 assertions).

### Validation
Full suite: 652 passed, 13 skipped, 0 failed (2886 assertions). Pint PASS; build PASS. Human visual review (desktop/mobile) APPROVED.

**Phase 3C.8 — API/Public Data Exposure & Security Surface:** COMPLETE — Gates A/B/C PASS. PublicProductResource added for public reads; controller isolation preserved; HTTP-level security assertions added. API tests 12/12 (77 assertions); web regression 74/74 (636 assertions); full suite 652 (639 passed, 13 skipped, 0 failed), 2919 assertions. Pint/PHP syntax/git diff --check clean. Changes uncommitted (per closeout).

## Public Surface Rate-Limiting Follow-up

**Status:** LOCAL COMPLETE / COMMITTED - NOT PUSHED
**Date:** 2026-10-12

### Objective
Rate-limit the four public product surfaces (web and API) per client IP while preserving the existing public contract.

### Implementation
- Named limiters `public-web` and `public-api`: 60 requests/minute keyed by `$request->ip()`, with separate web and API buckets.
- Routes: `/katalog` and `/produk/{sku}` use `throttle:public-web`; `/api/v1/products` and `/api/v1/products/{product}` use `throttle:public-api`.
- `/`, `/cara-order`, the existing `login` limiter, and authenticated and mutation routes are unchanged; the 429 envelope is preserved and the framework throttle headers are forwarded.
- No trusted-proxy configuration introduced.

### Validation
Focused rate-limit tests 12 passed / 692 assertions; regression 114 passed / 966 assertions; SQLite full suite 664 (651 passed, 13 skipped, 0 failed); MySQL 8.4.3 full suite 664 / 664 / 0 skipped / 0 failed (3,648 assertions). Pint, PHP syntax and `git diff --check` pass.

### Commit
Implementation committed locally as `e03d7ee`; this documentation recorded in a follow-up commit. NOT PUSHED.

### Open before production
Rumahweb client-IP (`REMOTE_ADDR` vs proxy) confirmation and any trusted-proxy decision; a safe production route-cache refresh mechanism; the 60/minute threshold remains provisional pending observed traffic. Deployment and production activation are NOT complete.

## PHASE 4 - Account Access & Approval Lifecycle

**Status:** LOCAL COMPLETE / NOT COMMITTED
**Date:** 2026-10-13

### Objective
Add self-service registration, email verification, an explicit account lifecycle, Inertia session auth, SUPERADMIN account review, and B2G evidence capture, without changing the existing Sanctum API contract or roles.

### Implementation
- Schema: users lifecycle columns (`status` NOT NULL default PENDING with NULL-sentinel grandfathering; `status_reason`, `status_changed_at`, `requested_role`) + append-only `b2g_evidence_submissions` (`unique(user_id, version)`).
- Services: `AccountLifecycleService` (register/approve/reject/suspend/reactivate/markEmailVerified/resubmit; strict audit in-transaction; post-commit notifications), `B2gEvidenceService` (versioned submit/review/recordAccess), `AuditLogger::strictLog()` + snapshot guard.
- HTTP: guest/signed auth routes, authenticated account routes, SUPERADMIN admin routes (policy + `password.confirm`); `account.active` middleware enforced on web and API; AuthController denies non-ACTIVE API logins.
- UI: 13 Inertia pages + `AccountLayout`; CLI `ajm:create-superadmin`.
- Tests: 52 new tests (registration, session auth, verification, password reset, resubmit, account-active middleware, admin review, suspend/reactivate, B2G review/download, superadmin protection, lifecycle service incl. strict-audit rollback, CLI command, migration defaults).

### Validation
`php artisan test` full suite: 716 total / 703 passed / 13 skipped / 0 failed (3,805 assertions). `pint --test` PASS; PHP syntax PASS; `npm run build` PASS; `git diff --check` clean. MySQL NOT exercised this pass.

### Open before production (blockers)
- First-SUPERADMIN provisioning path on Rumahweb Shared Hosting Small is unconfirmed (no SSH assumed).
- SMTP/authenticated sender unverified -> registration/verification/reset/status/admin mail is non-functional in production until configured.
- B2G activation blocked until the permitted evidence catalogue, mandatory NPWP/work-unit policy, and evidence retention/disposal rule are approved.
- Migration rollout needs a maintenance window, backup, and verified write-quiescence (MySQL DDL is not transactional).
- Secure cookies/HTTPS + verified trusted-proxy config; MFA for SUPERADMIN; explicit Sanctum token-lifetime/rotation decision.

## PHASE 4 - Account Access & Approval Lifecycle (Pre-Commit Security Review)

**Status:** REVIEWED / READY FOR COMMIT REVIEW / NOT COMMITTED
**Date:** 2026-10-13

### Objective
Independent pre-commit security and correctness review of the Account Access & Approval change set (tracked diff plus all new files) before any commit.

### Findings corrected
- `NewAccountApplicationNotification` threw while rendering for a `BUYER_B2G`-requesting applicant (backed-enum concatenation) and the failure was swallowed by the best-effort wrapper, so administrators received no new-application mail. Fixed to `?->value`.
- Broken action links in two mail templates (`url('/admin/accounts')`, `url('/login')`) replaced with `route('admin.accounts.index')` and `route('login')`.
- `AccountLifecycleService::approve()` audit `previous_state` now records `requested_role`.
- `throttle:account-status` (previously defined but unused) applied to `GET /status-akun/{user}`.
- Added `tests/Feature/Notifications/AccountMailTest.php` regression coverage.

### Reviewed and confirmed sound (no change)
- Signed GET verification performs no state change; the signed POST validates signature/expiry, user identity, and current-email binding without creating a session or token.
- `status` is the sole runtime gate; `email_verified_at` is an approval prerequisite, never an authorization gate; registration forces `BUYER_B2B`; `role`/`status`/`requested_role` are not mass-assignable.
- `account.active` enforced on authenticated web and every `auth:sanctum` group (closes pre-suspension tokens); admin mutations behind `password.confirm`; self-approval/suspension denied by policy and service.
- Last-active-superadmin and B2G version allocation serialize via `SELECT ... FOR UPDATE` on MySQL, with `unique(user_id, version)` as backstop; strict audit shares the state-change transaction so an audit failure rolls the change back.
- Evidence on the private `documents` disk (no public URL); download is authorized and strictly audited before bytes are served; enum-driven MIME allowlist (`mimes:pdf,jpg,jpeg,png`, max 5 MB); safe random stored filenames.
- Migration grandfathers legacy rows as ACTIVE without fabricating `email_verified_at`; new rows default PENDING; the NULL-sentinel backfill is retry-safe.

### Validation
SQLite full suite 719 total / 706 passed / 13 skipped / 0 failed (3,810 assertions); MySQL 8.4.3 full suite on the isolated `pengadaan_barang_ajm_testing` database 719 / 719 passed / 0 skipped (3,847 assertions). `pint` PASS; PHP syntax 53/53; `npm run build` PASS; `git diff --check` clean.

### Remaining limitations (unchanged blockers)
SMTP deliverability unverified; first-SUPERADMIN provisioning path on Rumahweb Shared Hosting Small unconfirmed; B2G evidence policy/retention pending approval; migration rollout window/backup; secure-cookie/HTTPS + trusted-proxy config; MFA for SUPERADMIN; explicit Sanctum token-lifetime decision; registration password minimum is 8 for public self-service while privileged CLI provisioning requires 12 — a deliberate policy difference (F5), now documented in code, not an oversight.


## PHASE 5 - Buyer Read-Only Self-Service Dashboard (Release 1)

**Status:** COMMITTED / PUSHED (`2bcddec`)
**Date:** 2026-10-14

### Objective
Add read-only buyer self-service surfaces (orders, RFQ, invoices, notifications) plus an enhanced account dashboard, without introducing buyer mutations, a new role/permission framework, schema changes, or public/API contract changes.

### Implementation
- Web/Inertia thin controllers: `Account\OrderController`, `Account\RfqController`, `Account\InvoiceController`, `Account\NotificationController`, plus an enhanced `Account\HomeController`.
- Routes (auth + account.active): /akun/pesanan, /akun/pesanan/{order}, /akun/rfq, /akun/rfq/{rfq}, /akun/tagihan, /akun/tagihan/{invoice}, /akun/notifikasi.
- Ownership scopes `scopeVisibleTo()` on Rfq/Order/Invoice/Payment (invoice via order, payment via invoice -> order); shared `formatPrice`/`formatDate` frontend util; AccountLayout buyer nav; GOV-04 disclaimer on each surface.
- Authorization: explicit policy check on every action (viewAny on list, view on detail); additive `viewAny` on InvoicePolicy + NotificationPolicy.
- Pages: Account/Orders/{Index,Show}, Account/Rfq/{Index,Show}, Account/Invoices/{Index,Show}, Account/Notifications/Index; Account/Home enhanced.

### Validation
SQLite full suite 745 total / 732 passed / 13 skipped / 0 failed (4,052 assertions); MySQL 8.4.3 full suite on the isolated `pengadaan_barang_ajm_testing` database 745 / 745 passed / 0 skipped / 0 failed (4,089 assertions). `pint --test` PASS; PHP syntax PASS (19 files); `npm run build` PASS; `git diff --check` clean.

### Open before production (blockers)
Unchanged from Phase 4: first-SUPERADMIN provisioning on shared hosting; SMTP deliverability; B2G evidence policy/retention; migration rollout window/backup; secure-cookie/HTTPS + trusted-proxy; MFA for SUPERADMIN; explicit Sanctum token-lifetime decision. Buyer mutations, admin dashboard, exports, and real-time notifications remain out of scope.


## PHASE 5 - SUPERADMIN Read-Only Console (Release 2)

**Status:** IMPLEMENTED / VERIFIED / COMMITTED LOCALLY (`4f9afa3`)
**Date:** 2026-10-15

### Objective
Add a read-only SUPERADMIN dashboard and operational queues (RFQ, orders, invoices, payments, audit) on the existing session/Inertia architecture, without mutations, exports, new roles/ACL, schema changes, or public/API contract changes.

### Implementation
- Console gate: `app/Policies/AdminPolicy.php` (no backing model; `view => SUPERADMIN`), registered via `Gate::policy(AdminPolicy::class, AdminPolicy::class)` in `AppServiceProvider`. Every admin action authorizes explicitly; detail pages re-authorize the record; audit enforces `AuditLogPolicy@viewAny`. No SUPERADMIN middleware, no new role/ACL.
- Controllers: `Admin\DashboardController`, `Admin\RfqController`, `Admin\OrderController`, `Admin\InvoiceController`, `Admin\PaymentController`, `Admin\AuditLogController` (all read-only).
- Routes (auth + account.active, prefix `admin`, name `admin.`): /admin/dasbor, /admin/rfq(/{rfq}), /admin/pesanan(/{order}), /admin/tagihan(/{invoice}), /admin/pembayaran(/{payment}), /admin/audit. Existing /admin/akun review flows untouched.
- Analytics reuse: extracted `app/Services/AnalyticsMetricsService::snapshot()` from the API `AnalyticsController@dashboard`; API now delegates (JSON contract unchanged) and the dashboard calls the service directly.
- Attention counters (bounded aggregates): pending accounts; evidence awaiting review; RFQs awaiting conversion; payments pending verification; invoices review-required; overdue invoices.
- Status labels single-sourced: `statusLabel()` added to `RfqStatus` and `OrderStatus`; buyer `Account\RfqController` and `Order::statusLabel()` delegate (labels unchanged).
- Privacy: never serialize proof/document URLs or B2G evidence path/disk; payment proof exposed only as a `has_proof` boolean (no download link - gap reported). Audit state snapshots rendered via an explicit scalar-only allowlist with a redacted count; PII never dumped; filters allowlisted (unknown ignored).
- Frontend: `AccountLayout` SUPERADMIN nav extended; shared `Components/Admin/{StatusFilter,GovNote}.vue`; pages under `Pages/Admin/{Dashboard,Rfq,Orders,Invoices,Payments,AuditLogs}`; GOV-04 disclaimer on every console surface.

### Validation
Figures below are from the implementation verification run (2026-10-15), not re-executed during this documentation correction: SQLite full suite 770 total / 757 passed / 13 skipped / 0 failed (4,326 assertions); MySQL 8.4.3 full suite on the isolated `pengadaan_barang_ajm_testing` database 770 / 770 passed / 0 skipped / 0 failed (4,363 assertions). 25 new admin tests. `pint --test` PASS; PHP syntax PASS; `npm run build` PASS; `git diff --check` clean. No schema migration, no API contract change, no role/auth change, and no production migration or deployment was performed.

### Open before production (blockers)
Unchanged: first-SUPERADMIN provisioning on shared hosting; SMTP deliverability; B2G evidence policy/retention; migration rollout window/backup; secure-cookie/HTTPS + trusted-proxy; MFA for SUPERADMIN; explicit Sanctum token-lifetime decision. Payment-proof download remains intentionally omitted pending an audited/protected route. Admin write actions (payment verification, tax recalculation, RFQ respond, order/BAST transitions, exports) remain out of scope for this read-only release.

## PHASE 6 - Buyer RFQ Creation (Web / Inertia) - Phase 1

**Status:** IMPLEMENTED / VERIFIED - COMMITTED LOCALLY (Phase 1 RFQ creation; not pushed)
**Date:** 2026-10-19

### Objective
Let a logged-in buyer create an RFQ from the browser (session/Inertia) by reusing the same domain rules as the Sanctum API instead of duplicating them, without schema changes, API contract changes, or a new role/permission framework.

### Implemented
- Shared writer `App\Services\RfqSubmissionService::submit(User, array): Rfq`. The API `Api\Rfq\RfqController@store` now delegates to it; the JSON envelope, 201 status, messages, validation rules, and role authorization are unchanged.
- Web flow: `GET /akun/rfq/create` + `POST /akun/rfq` on `Account\RfqController`, behind `auth` + `account.active`; write authorization via `RfqPolicy::createAsBuyer` (BUYER_B2B/BUYER_B2G only). The API `create` ability is untouched.
- `GET /akun/rfq/create` is registered before `GET /akun/rfq/{rfq}` (which now has a `whereUuid('rfq')` constraint). `App\Http\Requests\Account\StoreRfqRequest` mirrors the API rules; guests/inactive accounts redirect to login; SUPERADMIN receives 403.
- Audit: RFQ + items + `RFQ_CREATED` are written atomically via `AuditLogger::strictLog` inside the submission transaction; notifications are dispatched after commit.
- `auth-write` limiter (20/min per authenticated user + client IP) applied to both `POST /akun/rfq` and `POST /api/v1/rfqs`.
- Product selector: `base_price` (authoritative buyer-facing price source, mapped to `price`) and `stock` (only to derive the `available` boolean) are the query columns; the browser receives only the allowlisted `id, sku, title, price, available` projection (<= 200 rows). Raw `base_price`/`stock` keys are never serialized; out-of-stock products remain selectable.
- Pages: `Account/Rfq/Create.vue` plus a "Buat RFQ" CTA on `Account/Rfq/Index.vue` (non-buyers excluded); GOV-04 disclaimer on both.

### Validation
SQLite full suite 789 total / 776 passed / 13 skipped / 0 failed (4,528 assertions); MySQL 8.4.3 full suite on the isolated `pengadaan_barang_ajm_testing` database 789 / 789 passed / 0 skipped / 0 failed (4,565 assertions). 19 new tests. `pint --test` PASS; PHP syntax PASS; `npm run build` PASS; `git diff --check` clean. No migration, no API contract or role change, no deployment.

### Deferred / out of scope
BAST web signing (Phase 2) and payment-proof web submission (Phase 3), including all their open prerequisites (signing authority; proof rollback/reconciliation, access, and resubmission semantics). Buyer RFQ editing, admin RFQ response workflows, exports, supplier-side flows, API role-behavior changes, and new permission frameworks remain out of scope.