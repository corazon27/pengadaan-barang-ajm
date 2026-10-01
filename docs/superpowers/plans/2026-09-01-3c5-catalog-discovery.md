# Phase 3C.5 — Public Catalog Discovery (Search / Filter / Sort) — Master Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

> **Status:** GATE A APPROVED 2026-09-20 — backend discovery core + SEO delivery implemented and fully verified (SQLite + MySQL 8.4, Pint, diff check, initial-HTML SEO contract) and committed. GATE B APPROVED 2026-09-20 (human visual review) + IMPLEMENTED + COMMITTED as `bd7902c` (no push) — GATE B COMPLETE. **PHASE 3C.5 GATE C** — integration / production-readiness verification contract locked; awaiting human approval. No GATE C execution started.

**Goal:** Verify the already-completed 3C.5 Search + Filter + Sort implementation end-to-end (MySQL runtime proof, deterministic SQLite E2E fixture, full regression baselines, SEO, accessibility, payload boundary) and produce an integration / production-readiness verdict.

**Architecture:** GATE C changes no application code. It is a verification gate. The discovery contract is URL-driven and server-normalized; the E2E layer runs a temporary, git-ignored SQLite fixture behind a dedicated Laravel server on its own port, verified through the MCP browser, while the MySQL runtime proof is a separate Artisan/Tinker layer against the dedicated MySQL testing database.

**Tech Stack:** Laravel 13, PHP 8.3, Eloquent, Inertia + Vue 3 + Vite, Tailwind 4. PHPUnit Feature suites (SQLite + MySQL 8.4) + live MCP-browser E2E on PowerShell 5.1 / Windows. No JS test runner, no new E2E framework.

## Global Constraints

- PHASE 3C.5 GATE C is INTEGRATION / PRODUCTION-READINESS VERIFICATION, not feature development.
- Default behavior for GATE C: **ZERO APPLICATION CODE CHANGES**.
- GATE A commit `f4f2fd1`, GATE B commit `bd7902c`, and housekeeping `fe44950` are immutable.
- No commit, no push, no self-approval during GATE C without explicit human authorization.
- No `config/database.php`, `.env`, `.env.mysql`, or `phpunit.xml` redesign.
- No migrations, no seeders, no new indexes, no FULLTEXT, no new database connections.
- No new APIs, no new Resources, no new services, no new composables.
- No SSR, no caching architecture, no speculative performance architecture.
- The Laravel default connection remains `sqlite`; MySQL testing is activated only through process-environment overrides.
- There is no named `e2e` connection and no Sail in this repository.

---

## Goal (3C.5 feature scope — already implemented)

Add public catalog **discovery** to the `/katalog` page: **Search** (`q`), **Filter** (SNI / TKDN / availability), and **Sort** (strict allowlist), driven entirely by the URL as the source of truth, with browser Back/Forward support and page-reset on discovery change. **Category is explicitly deferred by design.**

## Scope decisions (locked 2026-09-01)

1. **Category is DEFERRED BY DESIGN, not a missing implementation.** It is not in 3C.5 scope.
2. **No category schema, migration, model, relation, seed, or UI in Phase 3C.5.** The only "category" concept in the docs (LKPP-02 category-gap matrix) is an *internal* procurement-compliance reporting concern (Module 9.6), not public discovery. Category remains a documented future **migration candidate** only.
3. **Existing `/api/v1/products` contract must not be silently broken or changed without an explicit API-evolution decision.** 3C.5 touches only the web `/katalog` path. The public API and `ProductResource` are left untouched.

## Architecture (3C.5 implementation — locked, unchanged by GATE C)

Keep `CatalogController` thin; encapsulate discovery in small, testable units (matches repo conventions: thin controllers, Enums, scopes):

- `app/Enums/CatalogSort.php` — backed enum; STRICT allowlist mapping `sort` → `[column, direction, tiebreak]`; `fromRequest()` returns default for unknown values (never interpolate raw input into SQL).
- **Correction (§AO #1/#5): no standalone `ProductFilter` value object.** Normalization is a private `CatalogController::normalizeDiscovery(Request)` helper returning a plain array `{q, sni, tkdn_min, availability, sort}`. `description` is **NOT** searchable (title+sku only).
- Model scopes on `Product`: `scopeSearch($q)` (grouped `where title like OR sku like %term%`), `scopeSni()`, `scopeTkdnMin($v)`, `scopeAvailable()` (`stock > 0`).
- `CatalogController::index` — call `normalizeDiscovery()`, apply scopes + allowlisted order, `paginate(12)`, then the **unchanged 9-key whitelist projection**.
- Confirm the public payload allowlist remains **exactly** `{id, sku, title, description, price, tkdn, hasSni, warrantyInfo, available}`; `stock` never serialized.

Rejected: Form Request validation (would 302 on garbage discovery input, fighting "URL = source of truth"); manual normalize/clamp (ignore invalid dims) is correct.

## Capability verdicts (evidence-based)

| Capability | Data available? | PRD/doc mandate? | Verdict |
|---|---|---|---|
| Search (`q`) | Yes: `title`, `sku` | Yes (API already grouped `%term%`, QRY-1) | Implement |
| Filter (`sni`, `tkdn`, `availability`) | Yes: `is_sni`, `tkdn_percentage`, `stock>0` | Yes (TKDN/SNI are B2G spec requirements, PRD §8) | Implement |
| Sort | Yes: `base_price`, `tkdn_percentage`, `created_at`, `id` | Yes | Implement (strict allowlist) |
| Category | **No model/table/column/seed** | **No** (PRD §8 requires TKDN/SNI, e-catalog status, price, specs — not category nav) | **Deferred by design** |

## URL contract (source of truth)

`GET /katalog` with optional query params:

| Param | Meaning | Normalization |
|---|---|---|
| `q` | search term (title **or** sku, case-insensitive) | trim, ≤120, strip control chars |
| `sni` | filter has-SNI | `1` = on; else off (no hard fail) |
| `tkdn_min` | min TKDN % | float, clamp 0–100; invalid ⇒ drop |
| `availability` | only in-stock (`stock>0`) | `1` = on |
| `sort` | `default`, `price_asc`, `price_desc`, `tkdn_desc`, `newest` | unknown ⇒ `default` |
| `page` | pagination | int ≥1; paginator clamps to lastPage |

**Locked parameter set (approved):** `q`, `sni`, `tkdn_min`, `availability`, `sort`, `page`.

**Sort allowlist mapping (never raw; final, no `title A-Z`):** `default`→`id asc`; `price_asc`→`base_price asc, id asc`; `price_desc`→`base_price desc, id asc`; `tkdn_desc`→`(tkdn_percentage IS NULL), tkdn_percentage desc, id asc` (portable SQLite+MySQL — known values, including 0, sort before NULL/unavailable; NULL always last); `newest`→`created_at desc, id asc`. Every sort carries an explicit `id asc` tie-break for deterministic pagination (UUID ids are time-ordered ⇒ insertion order).

**Page-reset:** a change to `q/sni/tkdn_min/availability/sort` resets `page`→1. **Back/Forward:** Inertia Link/router pushes each discovery state to history ⇒ native restore.

## Query / performance (MySQL 8.4 + SQLite tests)

- Fixed scope order; each filter = a bound WHERE on an existing column. Total = `COUNT` + `SELECT` (2 ops), no N+1.
- **No index migration now.** Table ~13 rows; filters low-selectivity; `%term%` not indexable (per repo policy); EXPLAIN evidence documentational only.
- **MIGRATION CANDIDATES (return only, do NOT implement):** (a) `created_at` index if `newest` sort at scale; (b) composite `(is_sni, stock)` if filter usage grows and EXPLAIN shows filesort on large data; (c) MySQL FULLTEXT `(title, sku)` only at thousands of rows. Each: {table, index, reason, expected query, verification = EXPLAIN on MySQL 8.4.3, rollback = drop index}.
- Build gate must EXPLAIN-verify final filtered+sorted shapes and confirm the default path matches baseline cost.

## API evolution (no change)

Public `GET /api/v1/products` and `ProductResource` are **untouched** by 3C.5. Discovery is the web `/katalog` path only. **Risk noted for a later phase (3C.8), not this one:** the *public* API index currently returns internal fields via `ProductResource` (`margin_percentage`, `tax_rate_percentage`, `estimated_shipping`, `stock`, `datasheet_url`) — recorded as an out-of-scope risk; must not be silently changed without an explicit API-evolution decision.

## Security & allowlists

- `sort`/columns/direction via strict enum allowlist only; no user text interpolated into SQL; all value params bound; order from enum table.
- `q` bounded length + control-strip; numeric clamp (`tkdn` 0–100, `page` ≥1); booleans accept only `1`; reject/replace array-flooded params (`$request->string()`); unknown dims ignored (no 500).
- Public payload remains exactly the 9 keys; `page` never leaks; `stock` never serialized.
- Public GET, read-only, no auth → no rate limit in 3C.5 (defer to 3C.8).

## Pagination interaction

Update `Pagination.vue`: `baseUrl` currently `/katalog` and `href = baseUrl?page=N` **drops discovery params**. Change to accept `baseUrl` as the discovery base (may contain `?`) and join page correctly (`&page=` when `?` present, else `?page=`). Server supplies the discovery query string from `normalizeDiscovery()` (page excluded). Preserve 44px targets + brand focus ring + `<span aria-disabled>` boundary pattern (GATE D fix must not regress).

## SEO / indexing strategy (SSR disabled) — locked

- Clean `/katalog` → canonical `APP_PUBLIC_URL/katalog` (production) + indexable, H1 + existing og/meta (unchanged).
- **Any discovery-active state** (`?q`, `?sni`, `?tkdn_min`, `?availability`, `?sort ≠ default`, `?page>1`) → `<meta name="robots" content="noindex,follow">` + canonical back to clean `/katalog`. Prevents faceted-URL explosion; search-result pages have no indexing value (indexable value flows through 3C.6 product-detail URLs).
- `page` is **excluded from the discovery echo** and from `pagination.baseUrl` discovery echo; `page > 1` only contributes to `hasActiveDiscovery` through the existing contract.
- Canonical and `og:url` follow the existing **production-only** contract. Vue does not emit competing canonical / robots / `og:url` metadata.
- No SSR; no server-rendered facet URLs.

## Frontend UX (discovery toolbar; procurement-native, anti-slop)

A single, calm **discovery toolbar** above the grid (not a mega filter sidebar, not pill fields, not a giant ecommerce search bar):

1. **Search** — compact labelled input "Cari produk (nama/SKU)" + submit; builds URL via Inertia `router.get` preserving other params; empty input drops `q`. Submit/Enter-based (no autocomplete/suggestions — no data justification).
2. **Filters** — small segmented/toggle controls: "Dengan SNI", "Tersedia", and a TKDN minimum selector. Implemented as real `<Link>`s (real anchors — each state a real URL, open-in-new-tab works). **Semantics: each filter is a real link whose accessible name communicates the action/state** (inactive → "Filter SNI" / "Filter tersedia"; active → "Hapus filter SNI" / "Hapus filter tersedia"). **NOT `aria-current`** (these are independent filters, not current-item navigation — multiple can be active simultaneously), **NOT `aria-pressed`**, **NOT `role="button"`** — they remain navigation links with native link behavior.
3. **Sort** — compact labelled native `<select>` "Urutkan": Terbaru / Harga terendah / Harga tertinggi / TKDN tertinggi, with clear selected state. (Native select — keyboard-native; `router.get` on change.)
4. **Active-filter summary + "Hapus filter" reset link to clean `/katalog`** — rendered as normal (non-live) content; the single result-status line carries the announcement.
- **EmptyState**: discovery-aware copy variant ("Tidak ada produk yang cocok dengan filter") when `hasActiveDiscovery`, preserving the existing calm visual (copy-only change + reset action link).
- Responsive: toolbar stacks on mobile; grid unchanged (`min-[520px]:2 / lg:3`).

## Accessibility — locked

- Labels on all inputs/selects (visible, small-caps — never placeholder-only).
- **Discovery filter toggles are real `<Link>` anchors** (URL-driven, open-in-new-tab works). Each independent filter communicates its **action/state through the accessible name**, NOT `aria-current`, NOT `aria-pressed`, NOT `role="button"`. Accessible-name contract: inactive → `Filter SNI` / `Filter tersedia`; active → `Hapus filter SNI` / `Hapus filter tersedia`. The visible label stays concise (e.g. "Dengan SNI") while `aria-label` carries the action-aware name. Native anchor keyboard behavior (Enter/Space activation, focus, open-in-new-tab) is preserved untouched.
- **Exactly ONE live region:** a primary `role="status"` / `aria-live="polite"` result-status line that announces the result count and (when discovery is active) a compact active-discovery summary in the same announcement. The visual ActiveFilters row is ordinary content, NOT a second live region. **One navigation → one announcement** (no redundant screen-reader chatter).
- Empty state retains `role="status"` since it *replaces* the result-status line when the matches grid is empty (still one region).
- keyboard Enter submits search, Tab moves naturally through real links; disabled pagination boundary stays `<span tabindex="-1" aria-disabled>` (non-focusable); `prefers-reduced-motion` respected; focus-visible ring on all controls; contrast tokens unchanged.

## Discovery state prop (locked)

Controller additionally passes the normalized discovery state to the frontend so the toolbar renders exactly what the server applied — **no client-side re-normalization**:

```
{
  q: string|null,
  sni: boolean,
  tkdn_min: number|null,
  availability: boolean,
  sort: 'default'|'price_asc'|'price_desc'|'tkdn_desc'|'newest'
}
```

- **`page` is NOT included** in `discovery` — it travels only via `pagination` (`currentPage`, `lastPage`, `total`, `baseUrl`).
- `tkdn_min` arrives as a number (or `null`) because the server already clamped it to 0–100 / dropped invalid values. The frontend displays it, never re-clamps/re-validates.
- `sort` arrives as the allowlisted enum value string (server already resolved unknown → `'default'`).
- The frontend consumes this shape verbatim; **no duplicate validation, clamping, or normalization** anywhere in Vue.

## Public payload boundary — locked

The public catalog product projection remains exactly:

```
{
  id,
  sku,
  title,
  description,
  price,
  tkdn,
  hasSni,
  warrantyInfo,
  available
}
```

Never exposed: `slug`, `margin`, `tax`, `shipping`, `NIB`, `NPWP`, `PKP`, bank data, private supplier data, private compliance data, or raw `stock`. Availability remains derived from `stock > 0`. TKDN / SNI remain presence-information fields, not verification claims.

## Test baselines — locked expected execution results

These are **expected runtime baselines** for the GATE C execution, not results of plan authoring:

| Suite | Total | Passed | Skipped | Failed |
|---|---|---|---|---|
| SQLite | 617 | 604 | 13 | 0 |
| MySQL | 617 | 617 | 0 | 0 |

- Do **not** modify tests to force these counts.
- If a future execution produces different counts: **STOP and reconcile**. Do not reconcile inside the same run.
- Suite commands: `php artisan test` (full feature/unit matrix) for each environment; `./vendor/bin/pint --test` must report 0 issues; `npm run build` must succeed.

---

# PHASE 3C.5 GATE C — EXECUTION CONTRACT (locked)

PHASE 3C.5 GATE C is **INTEGRATION / PRODUCTION-READINESS VERIFICATION** for the already-completed 3C.5 Search + Filter + Sort implementation. It is **NOT** a feature-development phase. Default behavior is **ZERO APPLICATION CODE CHANGES**.

If execution later proves a real defect, the only permitted protocol is:

```
reproduce → write a meaningful regression test → STOP → obtain human authorization → minimal fix → rerun verification
```

That protocol is not executed by this document.

## Defect protocol boundary

GATE C never silently patches. Any code change requires a STOP, an explicit human authorization, and a rerun of the full affected verification layer.

---

## 0. GATE C global lifecycle skeleton

This is the single canonical GATE C sequence. The outer `try/finally` **encompasses the entire lifecycle**: the preconditions 0.1–0.3 are inside the outer `try`, and the outer `finally` is step 3. Each numbered step appears exactly once. Cleanup ownership is explicit: the **outermost** `finally` alone owns the global config-cache restoration; no inner block may restore it.

```
GATE C START
    ↓
outer try {
    # ---------- preconditions: 0.1 - 0.3 (inside the outer try) ----------
    0.1 snapshot the global config-cache state ONCE (§2)
    0.2 verify locked commits are ancestors of HEAD (§14)
    0.3 reconcile the working-tree precondition (§14):
         KNOWN + RECONCILED + NON-AMBIGUOUS
         (an explicitly acknowledged documentation-only change may remain;
          there is no clean-tree requirement)

    # ---------- 1. MySQL runtime proof + MySQL suite ----------
    try {
        override MySQL env (§1)
        PROOF 1 machine-checkable MySQL runtime proof (§3)
        MySQL suite, ONE execution (§4)
    }
    finally {
        # inner cleanup: MySQL environment only
        restore MySQL environment exactly (§1)
    }

    # ---------- 2. SQLite / E2E lifecycle ----------
    try {
        override SQLite env (§7)
        migrate fresh temporary fixture DB
        assert products count == 0 (§6)
        populate exactly 26 pinned products (§6)
        assert products count == 26 (§6)
        start dedicated server on its OWN port; capture PID
        smoke gate (§8)
        E2E matrix (§10)
    }
    finally {
        # inner cleanup: E2E teardown only
        stop captured server if running
        verify process exit
        delete temporary fixture if present
        restore SQLite environment exactly (§1 model)
    }
}
finally {
    # ---------- 3. global final teardown: SOLE owner of the global config cache ----------
    restore global config-cache state ONCE (§2)
    remove temporary config-cache backup
}

GATE C END → human verdict (never self-approved)
```

Ownership summary:

| Scope | Owns |
|---|---|
| MySQL inner `finally` | restore MySQL environment exactly |
| SQLite/E2E inner `finally` | stop captured server, verify process exit, delete fixture, restore SQLite environment exactly |
| **Outer `finally` (step 3)** | **restore global config-cache state ONCE; remove the temporary config-cache backup** |

Critical rule: **global config-cache restoration belongs exclusively to the outer `finally` (step 3).** A per-suite or inner `finally` is never sufficient on its own, and there are no independent MySQL and SQLite config-cache restore cycles.

Because the outer `try` opens before 0.1, a controlled failure is possible at **any** of these points: step 0.2 (ancestor verification), step 0.3 (working-tree reconciliation), step 1 (MySQL proof or MySQL suite), step 2 (SQLite migration, fixture emptiness assertion, population, `count == 26` assertion, smoke gate, or E2E matrix).

Any controlled STOP or failure that exits the inner verification path still flows through the outer GATE C `finally` path, which restores the original config-cache state and completes teardown.

Scope of the guarantee: this guarantee applies to **controlled exits within the running PowerShell process** (normal completion, `throw`, `exit` within the scripted path). It does not claim cleanup for arbitrary hard termination of the parent PowerShell process (e.g. a kill of the host shell, power loss, or an external `Stop-Process` on the parent), where no `finally` can run.

No `config:clear` may run before step 0.1 has completed.

## 1. Windows-safe MySQL environment lifecycle

Environment: PowerShell 5.1 on Windows. Laravel's default connection stays `sqlite`; MySQL is selected for the child Artisan process only. There is no Sail, no Unix `VAR=value cmd` form, and no named e2e connection.

PowerShell-session-scoped environment overrides are inherited by child Artisan processes and are explicitly restored in `finally`. They are not assumed to disappear automatically when Artisan exits.

Capture-before-override, exact-restore contract (never a blind delete of all six variables):

```powershell
$envNames = @(
    'DB_CONNECTION',
    'DB_HOST',
    'DB_PORT',
    'DB_DATABASE',
    'DB_USERNAME',
    'DB_PASSWORD'
)

# 1) capture the PRE-EXISTING process state BEFORE any override
$envBackup = @{}
foreach ($name in $envNames) {
    $item    = Get-Item -LiteralPath "Env:$name" -ErrorAction SilentlyContinue
    $existed = $null -ne $item
    $envBackup[$name] = [pscustomobject]@{
        Existed = $existed
        Value   = if ($existed) { $item.Value } else { $null }
    }
}

try {
    # 2) override to the dedicated MySQL TESTING database only
    $env:DB_CONNECTION = 'mysql'
    $env:DB_HOST       = '127.0.0.1'
    $env:DB_PORT       = '3306'
    $env:DB_DATABASE   = 'pengadaan_barang_ajm_testing'
    $env:DB_USERNAME   = 'root'
    $env:DB_PASSWORD   = ''

    # ... 2.1 global config:clear + 2.2 MySQL proof + 2.3 MySQL suite run here ...
}
finally {
    # 3) restore each ORIGINAL value exactly; unset only originally-absent variables
    foreach ($name in $envNames) {
        $entry = $envBackup[$name]
        if ($entry.Existed) {
            Set-Item -LiteralPath "Env:$name" -Value $entry.Value
        }
        else {
            Remove-Item -LiteralPath "Env:$name" -ErrorAction SilentlyContinue
        }
    }
}
```

Rules:

- The development database `pengadaan_barang_ajm` MUST NOT receive fixture or verification writes; only `pengadaan_barang_ajm_testing` is touched.
- The `.env.mysql`-based dedicated testing database contract is preserved as-is; `config/database.php` is not redesigned.
- `Remove-Item Env:...` is used only for a variable proven to have been originally absent.
- If a human later intentionally sets a MySQL session variable, its pre-GATE-C value is exactly what `finally` restores.

## 2. Global config-cache lifecycle (single snapshot, whole gate)

This is **one** GATE C lifecycle, not per-suite and not per-layer.

At GATE C START, before the FIRST `config:clear`:

```powershell
$configCachePath = Join-Path (Get-Location) 'bootstrap/cache/config.php'
$configCacheBackup = Join-Path ([System.IO.Path]::GetTempPath()) ('3c5-gatec-config-' + (Get-Date -Format 'yyyyMMddHHmmssfff') + '.php')

$configCacheExisted = Test-Path -LiteralPath $configCachePath
if ($configCacheExisted) {
    Copy-Item -LiteralPath $configCachePath -Destination $configCacheBackup -Force
}
Write-Output ("config_cache_initial_state=" + $(if ($configCacheExisted) { 'EXISTS' } else { 'ABSENT' }))
```

The snapshot is captured **ONCE** and reused for every later step. The lifecycle is:

```
GATE C START
  → snapshot config-cache state once (EXISTS | ABSENT)
  → MySQL verification (config:clear allowed, source files untouched)
  → SQLite / E2E verification (config:clear allowed, source files untouched)
  → outermost final teardown
  → restore original config-cache state ONCE
```

At final teardown (inside the outermost `finally`, never inside a per-suite block):

```powershell
if ($configCacheExisted) {
    Copy-Item -LiteralPath $configCacheBackup -Destination $configCachePath -Force
    Remove-Item -LiteralPath $configCacheBackup -Force -ErrorAction SilentlyContinue
}
else {
    Remove-Item -LiteralPath $configCachePath -Force -ErrorAction SilentlyContinue
}
```

Rules:

- If the cache existed originally, the original cached state is restored verbatim.
- If it was absent originally, `config.php` must not remain after GATE C.
- Automatic re-creation of the cache is never assumed and never claimed.
- No independent MySQL and SQLite cache snapshots exist.
- Config **source** files are never modified during GATE C.
- The temp backup copy lives outside the repository working tree and is deleted after restore.

## 3. PROOF 1 — machine-checkable MySQL runtime proof

This proves the Artisan/test process resolves the intended Laravel runtime configuration against the dedicated MySQL testing database. It does **not** prove browser wiring.

Run under the SAME environment block as section 1. `DB_PASSWORD` is never printed, logged, echoed, or surfaced in evidence.

```powershell
    # ---- Step 1: clear the config cache, hard-gated on exit code ----
    php artisan config:clear --ansi
    if ($LASTEXITCODE -ne 0) {
        throw "PROOF 1 FAIL: 'php artisan config:clear' exited with code $LASTEXITCODE"
    }

    # ---- Step 2: Tinker under the SAME environment; capture output + exit code ----
    $tinkerScript = @'
$keys = ['driver', 'host', 'port', 'database'];
foreach ($keys as $k) {
    $v = match ($k) {
        'driver'   => DB::connection()->getDriverName(),
        'host'     => DB::connection()->getConfig('host'),
        'port'     => DB::connection()->getConfig('port'),
        'database' => DB::connection()->getDatabaseName(),
    };
    echo $k . '=' . $v . PHP_EOL;
}
'@

    $tinkerOutput = & php artisan tinker --execute=$tinkerScript 2>&1
    $tinkerExit   = $LASTEXITCODE

    # ---- Step 3: parse the ACTUAL key=value output into a map ----
    $observed = @{}
    foreach ($line in $tinkerOutput) {
        if ("$line" -match '^(?<k>[a-z]+)=(?<v>.*)$') {
            $observed[$Matches['k']] = $Matches['v']
        }
    }

    # ---- Step 4: assert the exact expected values; any failure is fatal ----
    $expected = [ordered]@{
        driver   = 'mysql'
        host     = '127.0.0.1'
        port     = '3306'
        database = 'pengadaan_barang_ajm_testing'
    }

    $proofFailures = @()

    if ($tinkerExit -ne 0) {
        $proofFailures += "tinker exit code was $tinkerExit"
    }

    foreach ($key in $expected.Keys) {
        if (-not $observed.ContainsKey($key)) {
            $proofFailures += "missing key: $key"
            continue
        }
        $actual   = ([string]$observed[$key]).Trim()
        $required = [string]$expected[$key]
        if ($actual -cne $required) {
            $proofFailures += "wrong value for ${key}: expected '$required', observed '$actual'"
        }
    }

    if ($proofFailures.Count -gt 0) {
        foreach ($failure in $proofFailures) { Write-Output ("PROOF 1 FAIL: " + $failure) }
        throw 'PROOF 1 FAILED — the MySQL test suite MUST NOT start after a failed proof'
    }

    Write-Output 'PROOF 1 PASS: driver=mysql host=127.0.0.1 port=3306 database=pengadaan_barang_ajm_testing'
    # ---- Step 5: control only — the MySQL suite is executed exactly once, in §4 ----
    # Proceed to §4 (single MySQL suite execution). Do NOT re-run anything here.
```

Assertion semantics:

- Non-zero `config:clear` exit → FAIL.
- Non-zero Tinker exit → FAIL.
- Any required key missing from the captured output → FAIL.
- Any captured value differing from the expected value → FAIL.
- Any failure → **STOP**; the MySQL suite does not start.
- Success is a machine pass/fail decision, not a human reading of terminal output. Human inspection of raw output is never the gate.
- `DB_PASSWORD` appears in no output line, thrown message, or evidence record.

## 4. MySQL suite — ONE execution, two separate gates

Runs only after PROOF 1 passes, inside the same environment block.

**The MySQL suite executes `php artisan test` exactly ONCE.** That single execution both produces the output that Gate 4b inspects and provides the exit code that Gate 4a checks. The suite is never run a second time merely to capture output.

**Gate 4a — exit code (hard execution gate):** did the command itself succeed?

```powershell
    $mysqlTestOutput = & php artisan test 2>&1
    $mysqlTestExit   = $LASTEXITCODE
    $mysqlTestOutput | Write-Output

    if ($mysqlTestExit -ne 0) {
        throw "GATE C FAIL (MySQL): 'php artisan test' exited with code $mysqlTestExit"
    }
```

**Gate 4b — actual reported test counts (separate verification evidence):** did it produce the locked result? A zero exit code does **not** prove the counts.

Reconcile the counts reported in the already-captured `$mysqlTestOutput` against:

| Suite | Total | Passed | Skipped | Failed |
|---|---|---|---|---|
| MySQL | 617 | 617 | 0 | 0 |

Rules:

- The two gates answer different questions and never substitute for each other. Reporting a zero exit code as proof of the counts is forbidden.
- Gate 4b is a **human-visible comparison of the real, already-captured Laravel summary line**. No bespoke output parser is required or permitted; the machine-checked mechanism in this gate is the PROOF 1 runtime assertion in §3, and count reconciliation is an explicit human comparison of the actual output.
- Any mismatch against the locked counts ⇒ **STOP and reconcile**. Do not re-run hoping for a different number, adjust, or force the result.
- Tests are **never** modified merely to restore counts.

## 4a. SQLite suite — same ONE-execution, two-gate structure

The SQLite suite also executes `php artisan test` exactly ONCE, with the same capture → emit → hard-gate → reconcile pattern:

```powershell
    $sqliteTestOutput = & php artisan test 2>&1
    $sqliteTestExit   = $LASTEXITCODE
    $sqliteTestOutput | Write-Output

    if ($sqliteTestExit -ne 0) {
        throw "GATE C FAIL (SQLite): 'php artisan test' exited with code $sqliteTestExit"
    }
```

Gate 4a = the exit code above. Gate 4b = reconcile the counts in `$sqliteTestOutput` against 617 total / 604 passed / 13 skipped / 0 failed. The 13 SQLite skips are expected and are not a failure.

## 5. PROOF 2 — E2E browser smoke (separate layer)

This separately proves:

```
MCP browser → dedicated Laravel server → fixture-backed Laravel application → temporary SQLite fixture
```

PROOF 1 and PROOF 2 never substitute for each other: the Tinker/MySQL proof says nothing about browser wiring, and the browser smoke says nothing about the MySQL runtime configuration.

E2E SQLite environment model:

- temporary SQLite fixture at a **git-ignored** path (never the development MySQL database),
- default `sqlite` Laravel connection,
- process environment override for `DB_CONNECTION` and `DB_DATABASE`,
- no named `e2e` connection,
- the dedicated server process identity / PID captured so teardown stops exactly that process.

## 6. Deterministic E2E fixture (locked)

Exactly **26** products; `paginate(12)` ⇒ exactly **3** pages; page 2 is guaranteed and deterministic. The primary page-2 URL is exactly `/katalog?sort=price_asc&page=2`. No Faker randomness on assertion-critical fields — all assertion-critical values are pinned.

Required deterministic coverage:

- 6 titles contain `meja`; 20 titles do not.
- 8 rows `SNI = true`; 18 rows `SNI = false`.
- 5 rows `stock = 0`; 21 rows `stock > 0`.
- TKDN values cover: `null`, `0`, `25`, `42`, `58`, `60`, `71`, `90`, `100`.
- Prices strictly increase `1..26`, so `sort=price_asc` equals insertion order and `sort=price_desc` is its exact reversal; page 2 under `price_asc` is therefore deterministic.

Composite positive invariant — exactly one row satisfies all of:

```
q = meja
AND SNI = true
AND TKDN >= 40
AND stock > 0
```

Negative invariants (each is separately observable):

- one `meja` row that fails the SNI condition (SNI = false, TKDN ≥ 40, stock > 0),
- one `meja` row that fails the TKDN condition (SNI = true, TKDN < 40, stock > 0),
- one `meja` row that fails availability (SNI = true, TKDN ≥ 40, stock = 0),
- at least one `SNI = true` row with `stock = 0`,
- at least one available (`stock > 0`) row with TKDN < 40.

The fixture is a **temporary** artifact of the E2E lifecycle. It is not a permanent seeder, and it is not created while this plan is authored.

### Ephemeral fixture population mechanism (allowed mechanism, closed)

The 26 rows are produced by an **ephemeral, execution-time population mechanism** built on the repository's **existing** model / factory infrastructure (the `Product` model and its existing factory, where present) with **explicitly pinned assertion-critical values**.

Allowed mechanism, in architectural terms:

- population runs **only during actual GATE C execution**, against the temporary SQLite fixture database only,
- it uses the existing model/factory layer — `Product::create([...])` or `Product::factory()->create([...])` with a fully pinned attribute array — and nothing else,
- **no committed permanent seeder class** is added to the repository,
- **no new migration**, **no new database connection**, **no new test framework**, no new fixture package,
- every assertion-critical field (sku, title, `is_sni`, `tkdn_percentage`, `stock`, `base_price`) is passed explicitly; **Faker is never allowed to generate any assertion-critical value**,
- non-assertion cosmetic fields may use the existing factory defaults,
- the fixture database is a **fresh throwaway file**: it is migrated, its `products` table is asserted to be **empty**, and then exactly 26 rows are populated. There is **no truncation, no `delete all`, and no defensive emptying step** — see the emptiness assertion below,
- the fixture database file is deleted during teardown (§7), so nothing survives the run,
- it never connects to, migrates, or writes the development MySQL database.

### Fresh-fixture emptiness assertion (replaces truncation)

```
fresh temporary SQLite DB (new throwaway file)
  → migrate
  → assert products count == 0
      if count != 0 → STOP
  → populate exactly 26 pinned products
  → assert products count == 26
      if count != 26 → STOP
```

- The `products` table of a freshly created, freshly migrated temporary SQLite database must start at exactly **0** rows.
- An unexpected non-empty `products` table is treated as a **fixture-isolation failure**, not as a condition to repair by destructive truncation.
- Therefore **no** `truncate`, `delete()`, or "empty the table defensively" instruction is part of this plan. Destructive cleanup of the table is explicitly the wrong response; the correct response is to STOP and investigate fixture isolation.
- After population the count must be exactly **26**. Anything else ⇒ STOP.

This section removes ambiguity about the allowed mechanism only. It is **not** implementation code and is **not** executed by this document.

## 7. E2E SQLite lifecycle (step 2 — inner scope, cleaned up by its own inner finally)

Detail expansion of step 2 in §0. This inner block owns E2E teardown only; it does not own the global config-cache restore.

```
capture prior SQLite env values (DB_CONNECTION, DB_DATABASE)
  → override DB_CONNECTION=sqlite, DB_DATABASE=<fresh temp git-ignored file>
  → config:clear with exit-code gate
  → migrate the fresh temporary fixture database
  → assert products count == 0, else STOP (fixture-isolation failure)
  → populate exactly 26 pinned products
  → assert products count == 26, else STOP
  → start the dedicated Laravel server on its OWN port; capture PID / process identity
  → smoke gate (§8) — STOP on any failure
  → E2E matrix (§10)
finally                                  # inner finally: E2E teardown only
  → stop the captured server process if still running; verify process exit
  → delete the temporary fixture database file if present
  → restore the captured SQLite env values (exactly as in §1)
```

Rules:

- The dedicated server process identity is captured, so teardown stops that exact process and no other.
- The fixture database file must not survive the run.
- The development MySQL database is never touched by this lifecycle.
- Any `config:clear` here is allowed; config source files are never modified.
- The global config-cache restore happens **only** in the outer `finally` (step 3 in §0), never in this inner `finally`.

## 8. E2E smoke gate (hard gate, BEFORE the matrix)

Smoke must confirm all of:

- the dedicated server is reachable on its own port,
- `GET /katalog` returns HTTP `200`,
- `pagination.total = 26`,
- `pagination.currentPage = 1`,
- `pagination.lastPage = 3`,
- at least one deterministic fixture SKU / title is visible in the rendered catalog.

Any smoke failure ⇒ **STOP**; the E2E matrix does not run.

Smoke makes **no** request-count claim, because smoke performs navigation rather than an instrumented single-action interaction.

## 9. Request-count evidence methodology (MCP network evidence)

For every applicable browser interaction:

```
baseline observation
  → perform EXACTLY ONE intended browser action
  → observe the network delta
  → count the intended discovery navigation request
  → exclude Vite / HMR / assets / fonts / favicon / preloads per the locked methodology
```

Rules:

- Request counts MUST be observed from actual network records.
- Request counts are NEVER inferred from Vue / Inertia / controller source code.
- "Exactly one request" is never claimed unless an actual browser action occurred in that step.
- A failed or ambiguous action yields an inconclusive count, not a favorable one.

## 10. E2E verification matrix

Preserve the existing deterministic matrix; no new E2E framework is introduced:

- clean catalog load,
- search (`q`) via type → Enter, grid + URL update,
- SNI filter toggle, URL update, page reset to 1,
- availability filter toggle,
- TKDN minimum selection,
- each sort value against the deterministic fixture,
- combined search + SNI + TKDN + availability,
- pagination with active filters (including the `&page=` join when `baseUrl` already carries `?`),
- deep link / direct load,
- refresh,
- browser back / forward,
- clear / reset discovery state back to clean `/katalog`,
- empty-result behavior,
- SEO in the initial HTML (canonical / `og:url` / `noindex` per the production-only contract),
- accessibility behavior (action-aware link names, exactly one live-region announcement per navigation, Tab focus ring, disabled-pagination boundary),
- responsive behavior (mobile stacking, grid unchanged),
- public payload boundary (exact 9 keys, no leak),
- runtime / build validation (`php artisan test`, `./vendor/bin/pint --test`, `npm run build`),
- visual regression evidence (screenshots for human review).

## 11. Visual approval boundary

The visual system is already human-approved (GATE B). GATE C must not self-approve visual quality and must not redesign anything. If a visual defect appears during future execution: capture evidence, document it, **STOP**, and require human confirmation before any design or code change.

## 12. Locked GATE C scope exclusions

GATE C contains no active scope for any of the following:

- JSON→MySQL work, Phase 1, Retro-Dongle,
- unrelated JSON resources, unrelated database writes,
- `config/database.php` redesign, `.env` redesign, `phpunit.xml` redesign,
- migrations, seeders, new database connections,
- Product Detail, category implementation, RFQ, Order,
- invoice / tax / B1 / Coretax, LKPP integration,
- SSR, caching architecture, speculative performance architecture,
- new testing framework, new composables, new services,
- unrelated Phase 3B or Phase 3C work,
- new indexes or FULLTEXT, new APIs, new Resources,
- push, self-approval.

## 13. Risk register (GATE C)

| Risk | Impact | Mitigation |
|---|---|---|
| MySQL suite runs against the development database | High | Dedicated `pengadaan_barang_ajm_testing` only, proven by PROOF 1 before any suite run |
| Environment block left mutated after the run | Med | Capture-before-override + exact restore in `finally`; never a blind delete |
| Working tree left with a different config-cache state | Med | Single global snapshot + single global restore in the outermost teardown |
| Tinker proof passes by accident on the wrong connection | High | Exact machine assertion on driver / host / port / database; any mismatch stops the gate |
| DB_PASSWORD leaks into evidence | High | Password is never printed, logged, echoed, or included in failure messages |
| Fixture is left behind after E2E | Med | Captured PID teardown + explicit fixture deletion, both mandatory |
| Request counts inferred rather than observed | Med | Baseline → single action → observed network delta only |
| Non-deterministic fixture values mask regressions | High | Pinned values for every assertion-critical field; no Faker on those fields |
| E2E matrix run on a failed smoke | Med | Smoke is a hard gate; failure stops before the matrix |
| Visual quality self-approved | Med | Screenshots presented; AI never self-approves |

## 14. Entry / exit criteria for GATE C

**Entry:** GATE A (`f4f2fd1`) and GATE B (`bd7902c`) committed and immutable; housekeeping `fe44950` intact; MySQL testing instance reachable at `127.0.0.1:3306`; dedicated SQLite fixture path writable and git-ignored; and the working-tree precondition below satisfied.

### Working-tree precondition (known + reconciled + non-ambiguous)

GATE C execution may proceed when the working-tree state is **known, reconciled, and cannot make the verification result ambiguous**. It does **not** require an artificially empty tree, and it never authorizes cleanup.

Before execution:

1. Inspect the current state (`git status --porcelain`).
2. Verify the locked commits are **ancestors of HEAD** using the authoritative ancestor-verification step below. No bounded commit-log window is accepted as proof of presence.
3. Enumerate **all** pre-existing modifications explicitly.
4. Classify every one of them as either:
   - **explicitly acknowledged documentation-only state**, or
   - **unresolved source / test / config change**.
5. Never: discard user changes, run `git restore`, run `reset --hard`, auto-stash, or silently overwrite a file.
6. An explicitly acknowledged **documentation-only** modification may remain in place when the human has accepted its presence **and** it cannot affect the GATE C verification result.
7. Any **unresolved source / test / config** modification that can affect verification ⇒ **STOP**.

Authoritative ancestor verification:

```powershell
git merge-base --is-ancestor f4f2fd1 HEAD; if ($LASTEXITCODE -ne 0) { throw 'GATE C STOP: f4f2fd1 is not an ancestor of HEAD' }
git merge-base --is-ancestor bd7902c HEAD; if ($LASTEXITCODE -ne 0) { throw 'GATE C STOP: bd7902c is not an ancestor of HEAD' }
git merge-base --is-ancestor fe44950 HEAD; if ($LASTEXITCODE -ne 0) { throw 'GATE C STOP: fe44950 is not an ancestor of HEAD' }
```

- Exit code `0` ⇒ that commit is confirmed as an ancestor of the current `HEAD`.
- Any non-zero exit ⇒ **STOP**.
- This step is read-only verification. History is never modified: no reset, no rebase, no amend, and no new commit during this stage.

The goal is **KNOWN + RECONCILED + NON-AMBIGUOUS**, not a blindly clean tree. An intentionally-present, human-accepted documentation-only change does not block execution. This precondition authorizes **no** cleanup actions and **no** changes to unrelated files; the human decides what to commit, stash, or leave in place.

**Exit:** PROOF 1 machine-passed; SQLite baseline 617/604/13/0 observed; MySQL baseline 617/617/0/0 observed; E2E smoke passed; E2E matrix passed; Pint clean; `npm run build` succeeded; SEO, accessibility, and payload boundary re-verified; environment and config-cache state restored; human verdict recorded. No push and no self-approval.

## 15. Gate structure (approved 3-gate layout)

- **GATE A — Backend discovery core:** `CatalogSort` enum + `Product` scopes + `CatalogController::normalizeDiscovery()` + URL contract + security allowlists + pagination baseUrl + `hasActiveDiscovery`/noindex prop + full unit & feature matrix + MySQL EXPLAIN verification. Human review. (headless, except the existing `Catalog.vue` Head receiving the noindex prop) — **APPROVED, committed `f4f2fd1`.**
- **GATE B — Frontend:** discovery toolbar in a single `DiscoveryToolbar.vue` (search field + sort select kept internal), `DiscoveryToggle.vue` (real-link toggle with action-aware accessible names), `ActiveFilters.vue`; Pagination `&page=` join fix; one `role="status"` result-status line; EmptyState discovery copy; responsive sweep + a11y. Human visual-approval gate — **APPROVED + IMPLEMENTED + COMMITTED as `bd7902c` (no push).**
- **GATE C — Integration / production-readiness verification:** this document. Human verdict only; never self-approved.

## 16. Skills used (evidence-contributing)

Process: `brainstorming`, `writing-plans`. Laravel/engineering: `laravel:api-resources-and-pagination`, `laravel:api-surface-evolution`, `laravel:performance-*`, `laravel:routes-best-practices`, `laravel:constants-and-configuration`, `laravel:eloquent-relationships`, `laravel:complexity-guardrails`, `verification-before-completion`, `improve-codebase-architecture` (at build); design: `anti-ui-slop`, `web-design-guidelines`, `frontend-design-deslop`.

## 17. Explicit decisions (persisted)

- **Category is DEFERRED BY DESIGN, not a missing implementation.** Not in 3C.5 scope.
- **No category schema, migration, model, relation, seed, or UI in Phase 3C.5.**
- **Existing `/api/v1/products` contract must not be silently broken or changed without an explicit API-evolution decision.**
- **§AO kickoff corrections applied:** (1) no standalone `ProductFilter` — private `normalizeDiscovery()` instead; (2) locked params `q/sni/tkdn_min/availability/sort/page`; (3) no `title A-Z` sort; (4) 3-gate structure; (5) `description` not searchable, no autocomplete.
- **Do NOT implement in 3C.5:** category (defer by design), API/ProductResource changes, SSR, autocomplete/suggestions, description-field search, mega filter sidebars, caching/CDN/Redis, any index migration (speculative), auth/registration, product-detail (3C.6), Cara Order (3C.7), security hardening incl. API field leak (3C.8).
- **Do NOT implement in GATE B (UI constraints):** separate `SearchField.vue`/`SortSelect.vue` files (fold into `DiscoveryToolbar.vue`); `aria-current`, `aria-pressed`, or `role="button"` on discovery filter toggles (real links with action-aware accessible names only); more than one live region for the same navigation; arbitrary free-text TKDN input; any client-side filtering/sorting of loaded products.
- **TKDN presets are a human product decision:** PRD §8 defines TKDN as evidence-gated indicators with no numeric thresholds; `min_tkdn=40` is a documentation example only. The UI offers a surfaced decision for human approval; no arbitrary TKDN text input unless explicitly approved.

---

# APPENDIX — GATE C FINDING F1 REMEDIATION (human-authorized, post-GATE C)

*Appended after the completed Gate C run. This appendix records a human decision and
its remediation. It does **not** alter the locked architecture, URL contract, payload
boundary, or any other §1–§17 decision above.*

## F1 — Default sort semantics

**Problem observed.** The sort control labels its default option `Terbaru` ("newest"),
but the default ordering did not deliver newest-first. `CatalogSort::DEFAULT` applied
`orderBy('id')`, and product ids are time-ordered UUIDv7 values, so ascending id order
is chronological **ascending** — i.e. oldest-first. The label promised the opposite of
the delivered order. Explicit `sort=newest` already produced correct newest-first
ordering via `created_at DESC, id ASC`.

**Human decision (Option B).** Keep the user-facing label `Terbaru`; change the default
ordering so it actually means newest-first, reusing the already-established newest
semantics rather than defining a second ordering.

**Minimal remediation.** One production-code change in `app/Enums/CatalogSort.php`:
`DEFAULT` and `NEWEST` share a single match arm, and the pre-existing standalone
`NEWEST` arm was removed, so the two modes cannot drift apart and exactly one
`created_at DESC, id ASC` clause remains in `CatalogSort`.

```php
// before
self::DEFAULT => $query->orderBy('id'),
// after (the trailing standalone self::NEWEST arm was deleted)
self::DEFAULT, self::NEWEST => $query->orderByDesc('created_at')->orderBy('id'),
```

Deterministic `id ASC` tie-breaking is preserved, so pagination stability is unchanged.
No other sort mode, filter, pagination rule, query-parameter normalization, or UI file
was touched. `DiscoveryToolbar.vue` still labels the default option `Terbaru`, unchanged.

**Verification result — PASS.**

| Layer | Result |
|---|---|
| Regression tests (RED first) | 5 new/updated tests failed against the pre-fix behavior, then passed |
| Focused catalog scope | 76 tests / 698 assertions passed |
| Targeted HTTP (deterministic 26-row fixture) | 24/24 passed; DEFAULT page-1 = `sort=newest` page-1 = indices 26→15 |
| Targeted browser | `Terbaru` label retained; DOM order newest-first; 1 request on load, 2 after one action (no duplicate fetch); 0 console errors/warnings |
| Other sort modes | `price_asc` 1→12, `price_desc` 26→15, `tkdn_desc` values unchanged |
| Pagination / filters | page 2, totals, `sni`, `availability`, `q`, and the compound discovery match all unchanged |
| SQLite suite | 620 total = 607 passed + 13 skipped + 0 failed |
| MySQL suite | 620 total = 620 passed + 0 skipped + 0 failed |
| Pint | `pint --test` passed |

The suite totals moved from the locked 617 baseline to 620 because this remediation adds
exactly three net-new regression tests. Failure and skip counts are unchanged.

## F2 — SEO metadata during Inertia navigation — NOT REMEDIATED

F2 is **out of scope** for this remediation and was deliberately left unimplemented.
Full-page loads continue to serve the correct server-rendered canonical / `og:url` /
robots metadata; client-side Inertia navigation does not update those values, which is
consistent with the locked SSR-disabled architecture. F2 remains recorded as
**KNOWN ARCHITECTURAL LIMITATION / FUTURE FOLLOW-UP**, pending its own authorization.

# FINAL CLOSURE RECORD (human-approved)

All four required human decisions were explicitly recorded before closure:

| Decision | Recorded value |
|---|---|
| F1 | **OPTION B** — `Terbaru` label preserved; DEFAULT is newest-first via `created_at DESC, id ASC`, shared with NEWEST |
| F2 | **FUTURE FOLLOW-UP REQUIREMENT** — no F2 implementation, no SSR redesign, no client-side SEO metadata work |
| Visual review | **APPROVED** (human judgement over `.playwright-mcp/gatec/` evidence) |
| Overall Gate C | **APPROVE AND CLOSE GATE C** |

- **Machine verification: PASS.** All Gate C machine gates passed, including the historical 617
  baselines (SQLite 604 passed + 13 skipped; MySQL 617 passed) and the post-F1 620 regression
  baselines (SQLite 607 + 13 skipped; MySQL 620), both executed once each.
- **F1 remediation: PASS.** B1–B3 correction pass completed; the final source has exactly one
  authoritative `self::DEFAULT, self::NEWEST` ordering arm, zero standalone `NEWEST` arms, and
  exactly one `created_at DESC` / `id ASC` clause.
- **Teardown: PASS.** Fixture deleted, dev server stopped, ports 8000/8001 free, config cache and
  temporary environment variables returned to their original absent state.
- **Visual review was judged by the human.** No AI self-approval was issued at any point.
- **Scope of this closure commit:** `app/Enums/CatalogSort.php`, `tests/Unit/CatalogSortTest.php`,
  `tests/Feature/Web/CatalogDiscoveryTest.php`, and this plan. The pre-existing unrelated
  `docs/CHANGELOG.md` modification is deliberately excluded from the commit and left untouched.
- **Gate C is CLOSED.** F2 remains an open future follow-up requirement, tracked separately and
  not authorized within Phase 3C.5.

PHASE 3C.5 GATE C — PLAN FINALIZED — AWAITING HUMAN APPROVAL
