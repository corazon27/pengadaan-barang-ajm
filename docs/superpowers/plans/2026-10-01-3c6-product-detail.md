# Phase 3C.6 — Public Product Detail — Master Plan

**Status: COMPLETE — VALIDATED AND HUMAN-APPROVED 2026-10-01. Implementation locked, both engines green, browser/SEO/accessibility validated, desktop visual review approved.**

---

## Global Constraints

- Do not modify implementation, tests, or redesign during documentation closure.
- Documentation closure touches exactly three files: this plan, `docs/MODULES_PROGRESS.md`, `docs/CHANGELOG.md`.
- No commit, no push at closure time.
- Historical Phase 3C.5 decisions are authoritative and are NOT rewritten by this document.

---

## Phase identity

| Attribute | Locked value |
|---|---|
| Phase | **3C.6 — Product Detail** |
| Route | `GET /produk/{sku}` |
| Route name | `produk.show` |
| Public identifier | **SKU** (not slug, not UUID) |
| Non-canonical SKU casing | **404** (never a redirect) |
| Not-found behavior | **Plain Laravel 404** (no custom 404 system) |
| Authorization | **Public.** No auth middleware, no `ProductPolicy` invocation, no new authorization framework |
| Projection | Dedicated six-field public projection |
| Framework | Inertia + Vue, SSR disabled |

### Authorization boundary (locked)

`ProductDetailController` performs **no** authorization call. `ProductPolicy::view(User $user, ...)`
is never invoked because it is not guest-safe. Rationale: `/katalog` is already a public discovery
surface, the six-field projection is non-sensitive by construction, and the route carries no auth
middleware. Introducing a guest-safe policy variant was explicitly rejected as out of scope.

---

## Final architecture

- `/katalog` remains the public **discovery** (collection) surface — Phase 3C.5, untouched.
- `/produk/{sku}` is the public **detail** (resource) surface — Phase 3C.6.
- A dedicated `App\Http\Controllers\ProductDetailController` renders the Inertia page.
- A dedicated six-field public projection is defined inline in that controller.
- `ProductResource` is **not** reused. `ProductResource` serves the authenticated/existing API
  surface; reusing it would couple the public web surface to the API contract and would risk
  widening the web payload to API fields.

### Public projection (locked — exactly six keys)

```php
'product' => [
    'sku'          => $product->sku,
    'title'        => $product->title,
    'description'  => $product->description,
    'price'        => (string) $product->base_price,
    'warrantyInfo' => $product->warranty_info,
    'available'    => (int) $product->stock > 0,
],
```

`price` is serialized as a **string** (`base_price` decimal) and `available` as a **boolean**.
`stock` is selected only to derive `available` and is then dropped — the raw count never reaches
the client.

The SKU was already part of the Phase 3C.5 catalog payload allowlist
(`{id, sku, title, description, price, tkdn, hasSni, warrantyInfo, available}`), so making the
catalog card navigational required **no payload change, no new backend field, and no slug exposure**.

---

## Security boundary

Only explicitly public columns are selected in the query:

```php
->select(['id', 'sku', 'title', 'description', 'base_price', 'warranty_info', 'stock'])
```

Excluded from the public Product Detail surface:

| Excluded | Reason |
|---|---|
| raw `stock` | internal inventory figure; only the derived boolean `available` is public |
| `margin_percentage` | internal commercial data |
| `tax_rate_percentage` | internal commercial data |
| `pph_rate_percentage` | internal commercial data |
| `estimated_shipping` | internal commercial data |
| `slug` | not needed; SKU is the public identifier; avoids a second public identifier |
| `datasheet_url` | private/internal document location |
| `tkdn_percentage` / `is_sni` | TKDN/SNI deliberately excluded from detail (see below) |
| `created_at` / `updated_at` | timestamps |
| NIB / NPWP / PKP | private company identifiers |
| bank details | private financial data |
| private supplier data | commercially sensitive |
| compliance data | compliance subsystem is out of scope |
| transaction data | transactional subsystem is out of scope |

Because the excluded columns are never even loaded, they cannot be serialized. This is enforced by
an allowlist assertion in `tests/Feature/Web/ProductDetailTest.php`, not by convention alone.

---

## Case sensitivity and canonical behavior (locked)

Resolution is **exact and case-sensitive**:

```php
$product = Product::query()->select([...])->where('sku', $sku)->first();

if ($product === null || $product->sku !== $sku) {
    abort(404);
}
```

### Why the PHP comparison is load-bearing

`products.sku` on MySQL 8.4.3 has collation **`utf8mb4_unicode_ci`, which is case-insensitive**.
A naive `where('sku', $param)->first()` would therefore match `lap-tkdn-001` against the persisted
`LAP-TKDN-001` and return **200**. SQLite's text comparison is already case-sensitive, so the same
query would return nothing. Comparing the hydrated persisted value against the request segment is
the single engine-agnostic enforcement point.

This collation was verified directly against the running MySQL 8.4.3 instance
(`information_schema.COLUMNS` → `COLLATION_NAME = utf8mb4_unicode_ci`), and the wrong-case `404`
behavior is asserted on **both** engines.

### Locked decisions

- Wrong-case SKU → **404**. A non-canonical casing is simply *not this product*.
- **No redirect.** Case is never normalized to redirect a client to the canonical casing.
- **No collation change.** No `COLLATE` clause, no `LOWER()`, no functional/expression index, no
  database-wide collation migration. These were all considered and rejected as heavier,
  engine-specific, or migration-requiring for zero user benefit.
- A genuinely **lowercase persisted SKU does resolve** (`srv-0001` → `200`), because resolution is
  by exact value equality, not by case folding.
- Canonical is derived from the **resolved persisted SKU**, never from the raw request parameter.

### Canonical generation (locked)

```php
route(
    'produk.show',
    ['sku' => $product->sku],
    absolute: false
)
```

The route parameter is passed **explicitly** because `route('produk.show', $product)` would resolve
through `Product`'s implicit route key — the UUID primary key — and silently emit
`/produk/<uuid>`. This is explicitly tested.

---

## SEO / F2 — the authoritative six-guarantee contract

Recorded exactly as implemented and verified. **No crawler-complete metadata claim is made.**

1. **No `noindex` on Product Detail.** The detail page is indexable.
2. **Exactly one canonical in production.** Server-rendered in the initial HTML document.
3. **Exactly one matching `og:url` in production.** Byte-identical to the canonical.
4. **Canonical is derived from the persisted SKU**, not from the UUID and not from the raw request
   segment.
5. **Non-production emits neither canonical nor `og:url`.** Both are gated on
   `app()->environment('production')` **and** a configured `app.public_url` (`APP_PUBLIC_URL`).
6. **Client-side Inertia navigation may leave Blade-level canonical / `og:url` tied to the initial
   shell** until a full document load. This is **F2**, inherited unchanged from Phase 3C.5 and
   **NOT REMEDIATED**.

Guarantees 2–5 were verified against a real production-mode server (`APP_ENV=production` plus
`APP_PUBLIC_URL`) serving the real initial document: exactly one `canonical`, exactly one `og:url`,
both `https://www.pengadaan-ajm.example/produk/LAP-TKDN-001`, identical to each other, with no
`noindex` and no UUID anywhere in the document.

**F2 remains a known architectural limitation / future follow-up**, consistent with the locked
SSR-disabled architecture. No SSR was introduced, and no client-side SEO metadata work was done.

---

## TKDN / SNI

- **Excluded from Product Detail.** `tkdn_percentage` and `is_sni` are not selected and not rendered
  on `/produk/{sku}`.
- **Existing catalog behavior is untouched.** The Phase 3C.5 catalog card continues to render its
  display-only TKDN/SNI presence markers exactly as before.
- **Reintroduction requires evidence-based justification and legal/regulatory review** before any
  future change.
- **`ProductCertification` was NOT created.** No certification subsystem, table, model, or UI.

The rationale for the split is deliberate: the catalog shows *presence flags* for procurement
comparison, while the detail surface is the record surface for a specific item and is held to the
narrowest defensible public field set.

---

## Media / publication state / datasheet — DEFERRED

All three are explicitly **deferred**, not partially built:

- **Media:** no media subsystem, table, model, upload, or gallery on the detail page.
- **Publication state:** no draft/published/archived state for products; no such column, model
  cast, filter, or UI.
- **Datasheet:** `datasheet_url` exists on the schema but is **not** exposed or linked publicly.
  Its usability as a public artifact requires a separate storage/authorization decision.

---

## Testing

### Final baselines (authoritative)

| Engine | Total | Passed | Skipped | Failed | Assertions |
|---|---|---|---|---|---|
| SQLite | **649** | **636** | **13** | **0** | **2877** |
| MySQL 8.4.3 | **649** | **649** | **0** | **0** | **2914** |

Pre-3C.6 baseline was `620 total / 620 passed` on both engines. The net delta is **+29 tests**, which
exactly matches the final `ProductDetailTest` count. Skip and failure counts are unchanged.

### Focused and cross-engine

- Focused pre-regression suite (ProductDetail + Catalog + CatalogDiscovery): **84 tests / 779
  assertions, 0 failed.**
- Focused Product Detail cross-engine subset on **MySQL**: **12/12 passed**, covering casing,
  canonical, lowercase-persisted, slash-SKU, unknown-SKU, zero-stock and allowlist behavior.

### `tests/Feature/Web/ProductDetailTest.php` (29 tests)

Covers public access; the exact six-key projection and its absence of excluded fields; `price`
string and `available` boolean typing; zero-stock returning `200` with `available: false`; plain
`404` for wrong case, mixed case, uppercased lowercase SKU, unknown SKU, slash SKU and raw UUID;
lowercase persisted SKU resolving; canonical using the persisted SKU rather than the UUID route key;
and the production/non-production SEO shell guarantees.

One test (`test_catalog_emits_the_product_detail_link`) was **removed** during validation: it asserted
against server-rendered raw HTML for a link that only exists after Vue runtime rendering. Under the
locked no-SSR architecture the anchor cannot exist in the initial HTML, so the assertion was invalid
rather than the feature being broken. It was replaced by
`test_catalog_payload_exposes_the_sku_needed_by_the_detail_link`, which asserts the actual
precondition (the catalog payload exposes `sku`). Browser/E2E is the authoritative navigation
verification layer for the rendered link.

### Quality gates

| Gate | Result |
|---|---|
| Pint | passed |
| `git diff --check` | clean |
| `npm run build` | passed — 563 modules transformed, no Vue compiler/template errors |
| Build warning | pre-existing optional `fontaine` dependency warning only; unchanged by 3C.6 |
| Build determinism | byte-identical asset hashes across runs |

### Browser / E2E validation

- catalog → detail → back passed; discovery state (`?sort=price_asc&page=2`) preserved across
  client-side navigation and restored on browser Back.
- Keyboard-only navigation passed: pure `Tab` traversal from the skip link through the 3C.5 discovery
  controls to the card link, then `Enter` to activate. The card link is `tabIndex 0` and carries the
  same focus-visible ring as every other focusable control.
- Skip link (`#main-content`) functional and first in tab order.
- Catalog card structure asserted in the live DOM: exactly one `<article>`, exactly one `<a>`, no
  nested interactive elements.
- Responsive: mobile **375px** passed with **zero horizontal overflow** and a single-column grid.
- Console: clean (the only entry on a 404 request is the document's own 404 status).
- HTTP status matrix: **13/13** — `200` for `/katalog`, `LAP-TKDN-001`, `srv-0001`, `OUT-0001`
  (zero stock), `NOWAR-001` (null warranty); `404` for lowercase and mixed-case wrong casing,
  `SRV-0001`, unknown SKU, `A%2FB`, raw UUID, empty and whitespace SKU. **No redirects** in any case.

### Dev-environment safety

E2E fixtures were written **only** to a throwaway SQLite database on a dedicated port, per the
Phase 3C.5 Gate C contract. The MySQL development database was never written to: it retains its
13 pre-existing rows (all created `2026-08-30`, none created during 3C.6), and no fixture SKU was
introduced into it. `.env`, `config/database.php` and `resources/views/app.blade.php` were not
modified. Both E2E servers were stopped, ports released, and all temporary fixtures deleted.

---

## Visual gate

- The human reviewer **APPROVED** the supplied desktop Product Detail and desktop Catalog
  screenshots.
- Browser and mobile visual validation passed independently.
- **No visual redesign was required or performed.**

---

## Known accepted limitations

- **SKU mutability can orphan old URLs.** `/produk/{sku}` is a permanent public URL keyed on a
  mutable business identifier. Editing a product's SKU leaves the previous URL as a `404`. There is
  **no redirect map** and no slug-based alias by design. A published SKU should be treated as
  effectively immutable.
- **The `/` and reserved-character limitation is inherited.** A SKU containing `/` is not
  addressable, because the default Laravel single-segment route pattern makes it unaddressable. No
  `where()` constraint was added: the schema defines no SKU character contract, and inventing one
  would silently change SKU write-path semantics.
- **SKU write validation remains pre-existing and out of scope.** The write path was not touched.
- **404 remains plain Laravel** — the unbranded default "404 Not Found" page with no links back to
  the catalog. This is the direct, locked consequence of "no custom 404 system". It is a known
  rough edge on a public site, not a defect against the contract.
- **F2 remains inherited.** Blade-level canonical/`og:url` stay tied to the initial shell across
  client-side Inertia navigation.
- **The pre-existing `fontaine` build warning** remains unchanged.

---

## Out of scope (explicitly not done in 3C.6)

- API remediation, including the public API internal-field leak tracked as 3C.8.
- `ProductResource` changes or exposing the web projection through the API.
- Any change to `/api/v1/products`, API controllers, requests, resources, or policies.
- Media subsystem, publication state, certification subsystem (`ProductCertification`).
- Module 9, dashboards, executive analytics, transaction workflows.
- SSR or any client-side SEO metadata remediation (F2).
- Rate limiting, additional middleware, custom 404 system.
- Category, migration or index changes, database schema changes.
- Shared `formatPrice` extraction (ProductDetail and ProductCard each keep their local formatter).
- SKU write-path validation, SKU mutability handling, redirect maps.
- Any change to Phase 3C.5 catalog behavior, discovery controls, or its payload.

---

## Key files created / modified

- `routes/web.php` — added `GET /produk/{sku}` → `produk.show`.
- `app/Http/Controllers/ProductDetailController.php` (new) — explicit projection, portable exact-case
  enforcement, plain `404`, explicit persisted-SKU canonical.
- `resources/js/Pages/ProductDetail.vue` (new) — detail page; reuses `PublicLayout`, `Container`,
  `Section`, `Button`, `ProductImage`; single `<h1>`, SKU, price, availability, warranty,
  breadcrumb/back navigation.
- `resources/js/Components/ProductCard.vue` — card became navigational; the whole card is one
  stretched Inertia `<Link>` to `/produk/{encodeURIComponent(sku)}` with no nested interactive
  elements. The 3C.5 information hierarchy, TKDN/SNI markers and visual treatment are preserved.
- `tests/Feature/Web/ProductDetailTest.php` (new, 29 tests).
- `docs/superpowers/plans/2026-10-01-3c6-product-detail.md` (new — this document).
- `docs/MODULES_PROGRESS.md` — Phase 3C.6 entry.

---

## Final closure record

| Item | Recorded value |
|---|---|
| Machine verification | **PASS** — SQLite 649/636+13, MySQL 649/649, focused 84, cross-engine 12/12, 13/13 HTTP matrix, Pint, diff-check, build |
| Visual review | **APPROVED** by human judgement over supplied desktop screenshots |
| Scope audit | **CLEAN** — no API, migration, `ProductResource`, `ProductPolicy`, `app.blade.php`, media, publication, SSR, or unrelated-refactor change |
| Commit status | **IMPLEMENTATION COMMITTED as `205d1c8`; documentation finalized in `6739077` and `5a28818`; NOT PUSHED** |

PHASE 3C.6 — IMPLEMENTATION AND VALIDATION COMPLETE — DOCUMENTATION CLOSED — COMMITTED — NOT PUSHED