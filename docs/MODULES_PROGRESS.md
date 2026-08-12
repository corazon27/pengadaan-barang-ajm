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