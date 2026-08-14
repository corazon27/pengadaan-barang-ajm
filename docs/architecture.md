# System Architecture & Technical Specification

**Project:** Standalone B2B & B2G E-Procurement Platform
**Framework:** Laravel 13 (PHP 8.3+)
**Database:** MySQL 8.4
**Document Status:** Authoritative architecture derived from `docs/PRD.md` (revised, Rev 3 — correction pass), `REGULATORY_RULEBOOK.md` v1.0, and the four approved final design decisions.

---

## 1. Core Architecture Philosophy & Compliance

Platform ini dirancang khusus sebagai sistem pengadaan mandiri (*single-supplier platform*) dengan model operasional ganda:

1.  **Siklus Transaksi B2B (Swasta):** Diproses secara langsung *end-to-end* di dalam platform, mulai dari penawaran harga (RFQ), penerbitan PO, pengiriman barang, BAST, hingga sistem penagihan berbasis *Term of Payment* (TOP).
2.  **Siklus Transaksi B2G (Pemerintah):** Platform berfungsi sebagai portal negosiasi teknis, katalog digital (internal mirror), dan pencetakan dokumen pra-pengadaan. AJM menyediakan **dukungan sisi penjual (supplier-side support)**, **handoff**, dan **referensi** ke kanal pengadaan resmi yang berlaku sesuai aturan (mis. e-Purchasing/e-Katalog LKPP sesuai aturan dan pengecualiannya, INAPROC, dsb.). **e-Katalog tidak diasumsikan sebagai metode B2G universal.** **GOV-01/04/05:** status, dokumen, dan referensi katalog internal **tidak pernah** dipresentasikan sebagai status/instrumen/rekaman pengadaan resmi. Metadata pengadaan eksternal disimpan terpisah (lihat §3.12).
3.  **Manajemen Dana Talangan:** Perhitungan piutang (*Receivables*) dikunci berdasarkan tanggal penandatanganan Berita Acara Serah Terima (BAST), bukan tanggal pemesanan awal.

**Pilar kepatuhan yang dipetakan ke komponen:**
- Model pajak dua-tahap (PRD §7) → `TaxRule` resolver, `CommercialTaxContext`, `AuthoritativeTaxResolution`, `RuleSnapshot` (§3.2).
- Pemisahan role / capability / legal function (PRD §4) → `Capability` catalog + `LegalFunctionAssignment` (§3.3).
- State machine order kanonik (PRD §5) → Order State Engine (§4) + external procurement metadata (§3.12).
- Module 9 = Compliance Foundation parent + submodules 9.1–9.8 (PRD §9) → Compliance kernel + bounded subdomain services (§3, §5).

**Principle for Modules 1–8:** `KEEP` / `EXTEND` / `MODIFY` / `DEPRECATE` — hanya struktur yang terbukti butuh perubahan (per approved reconciliation) yang diubah. Module 9 tidak diimplementasikan pada revisi ini; seluruh komponen di bawah adalah **design inputs**.

---

## 2. Technology Stack & Dependencies

*   **Core Framework:** Laravel 13 (PHP 8.3+ with `declare(strict_types=1);`)
*   **Database Engine:** MySQL 8.4 (Laragon): native ENUM, UUID/CHAR(36), JSON untuk Audit Log
*   **Frontend & UI Layer:** Blade Templating Engine + Tailwind CSS (Vite); ikonografi FontAwesome/Heroicons
*   **Document Rendering Engine:** `barryvdh/laravel-dompdf` atau `spatie/laravel-pdf`
*   **File Storage Engine:** Local Private Disk / S3 (uploads PO, BAST scan, e-Faktur, evidence)
*   **Task Scheduler:** Laravel Console Cron (monitoring jatuh tempo & overdue; statutory timers)
*   **Authentication & RBAC:** Laravel Sanctum + native Gates/Policies (token abilities `role:*`; diperluas dengan `cap:*` untuk capability catalog, §3.3)

---

## 3. Compliance Kernel & Module 9 Conceptual Components

Komponen berikut adalah desain konseptual (bukan kode). Parent "Compliance Foundation" (PRD §9) menyediakan **shared compliance kernel** yang dipakai semua submodule 9.1–9.8.

### 3.1 Compliance Kernel
| Component | Responsibility | Key Rule IDs |
|---|---|---|
| **RuleSnapshot** | Rekaman immutable aturan yang terselesaikan untuk suatu transaksi (rule version, effective range, formula, referensi legal). Basis non-retroaktivitas (§20). | TAX-PPN-01, §20 |
| **RegulatoryReference** | Registry referensi peraturan yang source-cited, effective-dated, berversi (dasar 9.7). | §20 |
| **HumanReviewCase** | Workflow kasus review manusia: konteks lengkap, reviewer berwenang, keputusan (approve/reject/escalate/record unresolved), timestamp + identitas, trigger re-review tahunan. | §18, ROLE-03/04/05 |
| **Capability catalog** | Registry izin aplikasi (`cap:*`) + mapping role→capability; enforcement oleh policies. | ROLE-01..08, SEC-03 |
| **LegalFunctionAssignment** | Rekaman penunjukan fungsi **regulatory/legal** (DPO = CONDITIONAL_LEGAL; lainnya = REGULATORY_LEGAL hanya bila basis statutori eksplisit) dengan basis, kategori, rentang efektif. **Nama RBAC tidak membuktikan fungsi legal.** Governance assignments (reviewer, tax reviewer, auditor, consent manager, breach owner) disimpan sebagai assignment berkategori `GOVERNANCE_OPERATIONAL` — **bukan** legal function default. | ROLE-02..08 |

### 3.2 Tax Domain (Submodule 9.1)
| Component | Responsibility | Key Rule IDs |
|---|---|---|
| **TaxRule resolver** | Registry aturan pajak **deterministik, berversi, source-cited**. Tidak ada rate hardcoded (`tax_rate=11`/`=12` invalid). Memecahkan: DPP method/formula, statutory rate, tax formula, effective burden, faktur code (hierarki), withholding flag. | TAX-PPN-01/02, TAX-PPH-01/02 |
| **CommercialTaxContext** | Tahap 1: freeze fakta komersial saat order (`unit_price_snapshot` = Harga Jual/unit, `line_base_amount_snapshot` = unit_price × quantity — dasar DPP per line; klasifikasi produk/buyer, status kolektor (nullable `UNVERIFIED`), tipe transaksi, referensi PKP, rule_version order-date). **Provisional / non-authoritative.** | TAX-PPN-01 |
| **AuthoritativeTaxResolution** | Tahap 2: resolve TaxRule pada **event pajak otoritatif** menggunakan fakta transaksi berlaku + aturan efektif; menghasilkan `RuleSnapshot` **per line item**; satu invoice memuat **banyak** snapshot via `invoice_rule_snapshots` (tidak diasumsikan 1 invoice = 1 rule). Jika pemicu legal belum pasti → state `REVIEW_REQUIRED`/`UNRESOLVED`/`PENDING_LEGAL_REVIEW`/`APPLICABILITY_UNKNOWN`, bukan kesimpulan deterministik. | TAX-PPN-01/03 |
| **BuyerClassification** | Klasifikasi pembeli (regular, government, BUMN, designated collector, dst.) sebagai dimensi input resolver. | TAX-PPN-01/02 |
| **VATCollectorStatus** | Status pemungut PPN instansi pemerintah (PMK 59/2022) — kondisional, butuh verifikasi (HUMAN REVIEW); **jangan diasumsikan** untuk semua pelanggan pemerintah. | TAX-PPN-04 |
| **RuleSnapshot** (kernel) | Output otoritatif yang disimpan pada invoice/rekaman pajak. | TAX-PPN-01/03 |

**Interaksi:** Order creation → `CommercialTaxContext` ditulis pada `order_items`. Event pajak → `AuthoritativeTaxResolution` membaca context + `TaxRule` (via `BuyerClassification`/`VATCollectorStatus`) → menulis `RuleSnapshot` per line item; invoice memuat banyak snapshot via `invoice_rule_snapshots` + data faktur. Perbedaan order-time vs event-time rule dicatat; invoice historis tidak pernah di-resolve ulang.

**Source of truth faktur code (TAX-PPN-02/03):** `faktur_codes` = **reference/rule catalog** (kanonik); `TaxRule` (`tax_rules.faktur_code`) = **rule ter-resolve** yang mereferensikan code dari katalog; `RuleSnapshot` (`rule_snapshots.faktur_code`) = **snapshot transaksi immutabel**. Tidak ada dua representasi otoritatif dari rule kode yang sama. **TAX-PPN-03 = E-Faktur Issuance & Reporting** (perakitan data E-Faktur/SIAP dari snapshot), **bukan** "faktur code 03". **TAX-PPN-06 (SPT Tahunan PPN) → NOT APPLICABLE**, tidak diimplementasikan (DB = N/A; regression/negative requirement).

### 3.3 Identity & Authorization Domain (Kernel + M1)
| Component | Responsibility | Key Rule IDs |
|---|---|---|
| **Organizational role** | Enum `user_role` (`SUPERADMIN`, `BUYER_B2B`, `BUYER_B2G`) — wadah penugasan. | ROLE-01 |
| **Capability** | Izin operasi (`product.manage`, `invoice.issue`, `review.decide`, `dsr.process`, `consent.manage`, `incident.respond`, `retention.manage`, `taxrule.manage`, `labeling.override`, dsb.) enforced oleh policies; token `cap:*`. | SEC-03 |
| **GovernanceAssignment** | Penugasan tata kelola/operasional internal (reviewer ROLE-04, tax reviewer ROLE-05, auditor ROLE-06, consent manager ROLE-07, breach response owner ROLE-08). **Bukan** statutory legal function secara otomatis; diangkat hanya dengan basis statutori eksplisit. | ROLE-04..08 |
| **LegalFunctionAssignment** | Catatan penunjukan fungsi **regulatory/legal** — **canonical source of truth** (kategori `CONDITIONAL_LEGAL` untuk DPO; `REGULATORY_LEGAL` hanya jika basis statutori eksplisit) (basis, tanggal, rentang). DPO hanya jika ditunjuk & applicability tuntas. `dp_roles` (bila dipertahankan) = **proyeksi khusus-DPO**, bukan source of truth kedua. | ROLE-02/03 |

**Anti-inference rule:** capability menegakkan aksi; legal function mengesahkan keputusan review. `SUPERADMIN` ≠ DPO/reviewer/tax officer. **Consent Manager (ROLE-07) dan Breach Response Owner (ROLE-08) tidak otomatis menjadi statutory legal function** — keduanya governance/operational assignment.

### 3.4 Supplier & Product Eligibility Domain (Submodule 9.2)
| Component | Responsibility | Key Rule IDs |
|---|---|---|
| **SupplierEligibility** | Rekaman bukti kelayakan penjual: NIB, NPWP, KBLI, KSWP, status PKP (referensi; `UNVERIFIED` precondition). Klaim kelayakan tanpa bukti → blokir. | GOV-02, TAX-PPN-05, PMSE-01/02 |
| **ProductCertification** | Rekaman bukti TKDN/SNI per produk (nomor sertifikat, penerbit, expiry, dokumen). Tidak ada auto-% TKDN. Klaim tanpa bukti → blokir. | TKDN-01/02/03/05, PROH-03 |
| **ECatalogStatus** | Status listing katalog produk sendiri (`NOT_LISTED`/`LISTED`/`VERIFIED`/`EXPIRED`). `lkpp_product_url` = REFERENCE, bukan bukti. | LKPP-05/06, PROH-02 |

### 3.5 PSE Domain (Submodule 9.3)
| Component | Responsibility | Key Rule IDs |
|---|---|---|
| **PSERegistration** | Rekaman pendaftaran PSE lingkup privat (nomor, tanggal, data pemeliharaan). | PSE-REG-001/002/003 |
| **PSECertificate** | Rekaman Sertifikat Elektronik PSE via PSrE Indonesia + expiry flag; **distinct** dari TTE perorangan/certified TTE signing (B2B-04). | PSE-CERT-001, §8 |
| **PSEContinuity / user assistance** | Evidensi keandalan, keberlangsungan, bantuan pengguna (cross-cutting M1–8). | PSE-GOV-001/002/003, PSE-SEC-001 |

### 3.6 PDP / Privacy Domain (Submodule 9.3)
| Component | Responsibility | Key Rule IDs |
|---|---|---|
| **DataSubjectRequest** | Alur intake + fulfillment hak subjek data; deadline per hak (hanya PDP-RIGHT-005/007 membawa 3×24h). | PDP-PROC-001, PDP-RIGHT-001..009 |
| **ConsentRecord** | Rekaman persetujuan per transaksi/pemrosesan + penarikan. | B2B-01, ETS-03, PDP-RIGHT-005, SEC-07 |
| **StatutoryTimer** | Timer 3×24h deterministik: penarikan persetujuan (Ps 40(2)), pembatasan/pembekuan (Ps 41(1)), notifikasi pelanggaran (Ps 46(1)). **`statutory_timers.deadline_at` = SOURCE OF TRUTH**; field turunan (mis. `consent_records.withdrawal_deadline_at`) hanyalah cached projection — divergensi dicegah. **Enforcement per tipe timer (bukan generic BLOCK):** consent withdrawal → `STOP_PROCESSING` (hentikan/blokir pemrosesan relevan); restriction → `SUSPEND_RESTRICT_PROCESSING` (suspend/batasi pemrosesan); breach notification → `ESCALATION_VIOLATION_AUDIT` (eskalasi + state violation/audit + alur notifikasi — **bukan** blokir pemrosesan). | PDP-3X24-001/002/003, PROH-05/07 |
| **Incident/Breach** | Rekaman insiden dengan **klasifikasi terpisah**: PDP breach (INC-PDP-001), gangguan serius PSE (INC-PSE-001), kegagalan PDP dalam PSE (INC-PSE-002); notifikasi (PDP-BREACH-001). | INC-*, PDP-BREACH-001, SEC-02, PROH-07 |
| **DPO trigger** | Penilaian applicability DPO — `PROFESSIONAL LEGAL/TAX REVIEW`, conditional; tidak deterministik. | PDP-DPO-001, ROLE-02 |

### 3.7 Evidence & Documents Domain (Submodule 9.4)
| Component | Responsibility | Key Rule IDs |
|---|---|---|
| **DocumentEvidence** | Registry dokumen legal per transaksi (checklist DOC-01), hash integritas, retrievability, label mirror/original + disclaimer. | DOC-01/02/03, GOV-01, PROH-01, B2B-02 |
| **RetentionPolicy** | Engine kebijakan retensi per dokumen (§19); **tanpa default universal**; konflik/unresolved → `REQUIRE_REVIEW`. | DOC-04, PROH-08 |
| **Template versioning** | Template berversi dengan legal-review sign-off sebelum publikasi. | DOC-05, GOV-01 |

### 3.8 Human Review Domain (Submodule 9.5)
`HumanReviewCase` (kernel §3.1) dipakai oleh semua submodule: labeling, channel, PSE/PDP kasus, tax exceptions, retention conflicts. Reviewer/tax reviewer = **governance assignment** ROLE-04/05 (bukan legal function default); untuk kesimpulan hukum, peninjau profesional (legal/tax) diwajibkan dan tercatat; keputusan tercatat; re-review tahunan (§20).

### 3.9 Regulatory Change Management (Submodule 9.7)
`RegulatoryReference` (kernel) + proses §20 (MONITOR→…→AUDIT) + watch items. **Cross-cutting governance capability**, bukan runtime dependency yang memblok submodule 9.x lain; `RegulatoryReference`/effective-dated rule versioning tinggal di shared kernel (dipakai 9.1, 9.5, dsb.). **Tidak ada perubahan regulasi yang diimplementasikan langsung di kode tanpa update aturan.** Effective-date + source_version + rule_version di setiap aturan time-sensitive.

### 3.10 Security Governance (Submodule 9.8)
`EXTEND` M1–8: SEC-01/03/04/05/07 PARTIAL; SEC-02 (incident response plan) → rekaman governansi; **SEC-06 (third-party processor agreements) → `processor_agreements` (rekaman governansi)**; sisanya menyatu lintas modul. PSE-SANC-001 (awareness record) + GOV-05 (ToS boundary clause) → governansi.

### 3.11 Procurement Channel Intelligence (Submodule 9.6)
Abstraksi channel/metode pengadaan: method awareness (GOV-03), e-purchasing warning sesuai aturan & pengecualiannya (LKPP-01; e-Katalog bukan metode B2G universal), INAPROC awareness (LKPP-03), category gap matrix (LKPP-02), non-catalog integrity guard (LKPP-06, PROH-02). Warning/block dengan human review untuk nuansa PPK.

### 3.12 External Procurement Reference / Status
**Bukan source of truth internal.** Field: `external_procurement_reference`, `external_procurement_status`, `external_system`, `external_status_verified_at`. Ditulis hanya dari input eksternal terverifikasi; tidak pernah menimpa `orders.status` (GOV-04).

---

## 4. Order State Engine & Flow Validation

**State machine kanonik (deployed enum — tidak diubah; keputusan desain #3):**

```
PENDING_PAYMENT → PROCESSING → SHIPPED → DELIVERED → COMPLETED
                                                   ↘ CANCELLED (terminal)
```

Dikendalikan oleh kelas `App\Services\OrderService.php`; transisi baku dan tidak dapat dilompati. **Payment state berada di domain invoice** (`invoices.status`: `UNPAID`/`OVERDUE`/`PAID`/`PARTIALLY_PAID`), bukan status order. Sub-peristiwa BAST/Invoice: `bast_documents.status` (`PENDING_SIGNATURE`/`SIGNED`) dan `invoices.*`.

**External procurement metadata** ditambahkan sebagai kolom pesanan (bukan overload `order.status`) — lihat §3.12 / PRD §3.

**Mapping legacy → kanonik (untuk dokumentasi & kompatibilitas):**
`DRAFT`→`PENDING_PAYMENT` · `WAITING_PO`→`PENDING_PAYMENT` · `PROCESSING`→`PROCESSING` · `SHIPPED`→`SHIPPED` · `BAST_SIGNED`→`COMPLETED` · `INVOICED`→`COMPLETED` · `PAID`→`COMPLETED` + `invoices.status=PAID` · `CANCELLED`→`CANCELLED`.

---

## 5. Document Engine, Financial Engine & Audit Trail

### A. Document Engine (PDF & Files Management)
Modul untuk membuat dan menyimpan dokumen hukum:
*   **RFQ Generator:** Surat Penawaran Harga ber-Kop Surat (PDF) berlabel **cermin internal** — GOV-01, DOC-03.
*   **BAST Management:** Draf BAST + unggahan scan BAST bertanda tangan.
*   **Invoice & Tax Engine:** Generasi Invoice berbasis `signed_date` + `top_days`; data E-Faktur/SIAP dirakit dari `RuleSnapshot` **per line item** (PRD §7), diagregasi ke invoice via `invoice_rule_snapshots` — TAX-PPN-03.

### B. Financial & Receivables Engine (TOP Monitoring)
*   **Jatuh Tempo:** $Due Date = Signed Date (BAST) + TOP Days$.
*   **Cron Job Scheduler:** Harian pukul 00:00 cek `invoices`; jika melewati due date & masih `UNPAID` → `OVERDUE` + notifikasi pengingat.

### C. Audit Trail & Compliance Log Subsystem
Setiap aksi kritis dicatat ke `audit_logs` (`App\Services\AuditLogger`): perubahan harga produk, status pesanan, penerbitan invoice, unggah BAST, auth events; menyimpan `previous_state`/`new_state` JSON. **PSE-AUDIT-001:** audit trail M8 dipetakan ke PP 71/2019 Ps 22(1) melalui audit-trail adapter + `audit_trail_mapping`; akses lihat log = capability `audit_log.view` (ROLE-06).

### D. Statutory Timers (Submodule 9.3)
Scheduler terpisah untuk timer 3×24h (consent withdrawal, restriction/suspension, breach notification) dengan **enforcement per tipe timer** — deterministik, distinct dari overdue scheduler: consent withdrawal → `STOP_PROCESSING`/hentikan-blokir pemrosesan relevan (PROH-05); restriction → `SUSPEND_RESTRICT_PROCESSING`/suspend-batasi pemrosesan; breach notification → `ESCALATION_VIOLATION_AUDIT`/eskalasi + violation/audit state + alur notifikasi (PDP-BREACH-001, PROH-07), **bukan** blokir pemrosesan (PDP-3X24-001/002/003).

---

## 6. Interaction & Dependency Map (Module 9 design)

**Dependency order:** (0) Compliance kernel (RuleSnapshot, RegulatoryReference, HumanReviewCase, Capability catalog, LegalFunctionAssignment) → (1) 9.1 Tax & Fiscal Rules → (2) 9.5 Human Review → (3) 9.2 Supplier & Product Eligibility → (4) 9.4 Evidence & Documents → (5) 9.3 PSE/PDP Compliance → (6) 9.6 Channel Intelligence → (7) 9.8 Security Governance. **9.7 Regulatory Change Management = cross-cutting governance capability** (`RegulatoryReference` + effective-dated rule versioning di shared kernel); **bukan** dependency runtime yang memblok submodule lain.

**Dependency edges (Ringkas):**
- `TaxRule` resolver ← `CommercialTaxContext` (order), `BuyerClassification`, `VATCollectorStatus`, `RegulatoryReference`; → `RuleSnapshot` → invoice.
- `SupplierEligibility`/`ProductCertification` → `ECatalogStatus` → Channel Intelligence (9.6) & Tax classification (9.1).
- `HumanReviewCase` ← labeling/channel/PSE/PDP/tax exceptions; → `LegalFunctionAssignment` (reviewer).
- `DataSubjectRequest`/`ConsentRecord` → `StatutoryTimer` → `Incident/Breach` (notifikasi).
- `DocumentEvidence` → `RetentionPolicy` (memakai `RegulatoryReference` per §19).
- `PSERegistration`/`PSECertificate` → PSE subdomain; `Incident/Breach` shared.
- Semua submodule → `Capability` catalog (akses) & `audit_logs` (trail).

---

## 7. Peta Struktur Folder Proyek (Laravel 13 Convention)

```text
GEMINI-PENGADAAN/
├── app/
│   ├── Enums/
│   │   ├── UserRole.php            # SUPERADMIN, BUYER_B2B, BUYER_B2G (organizational)
│   │   ├── RfqStatus.php
│   │   ├── OrderStatus.php         # PENDING_PAYMENT..COMPLETED, CANCELLED (canonical)
│   │   └── InvoiceStatus.php       # UNPAID, OVERDUE, PAID, PARTIALLY_PAID
│   ├── Http/
│   │   ├── Controllers/            # Admin/, Buyer/, Auth/ (+ future Module 9 subdomains)
│   │   ├── Middleware/             # EnsureUserRole, Capability middleware
│   │   └── Requests/
│   ├── Models/
│   │   ├── User.php, Product.php, Rfq.php, Order.php,
│   │   ├── BastDocument.php, Invoice.php, AuditLog.php
│   │   └── Compliance/             # (Module 9 design): TaxRule, RuleSnapshot,
│   │                               #  CommercialTaxContext, SupplierEligibility, ...
│   ├── Policies/                   # OrderPolicy, RfqPolicy, InvoicePolicy, Capability-enforcing
│   ├── Services/
│   │   ├── OrderService.php        # canonical state machine
│   │   ├── InvoiceService.php      # TOP & Due Date
│   │   ├── PdfService.php
│   │   ├── AuditLogger.php
│   │   └── Compliance/             # (design) TaxRuleResolver, StatutoryTimer, ...
│   └── View/Components/
├── bootstrap/                      # app.php, providers.php
├── database/
│   ├── migrations/                 # Pemetaan 1:1 dari database_schema.md (additive)
│   └── seeders/
├── docs/                           # PRD.md, architecture.md, database_schema.md,
│                                   #  REGULATORY_RULEBOOK.md, REGULATORY_COMPLIANCE_AUDIT.md
├── resources/
│   ├── css/, js/, views/           # admin/, buyer/, pdf/, compliance/ (design)
├── routes/                         # web.php, api.php, console.php
└── storage/app/private/            # uploads/po/, uploads/bast/, uploads/faktur/, uploads/evidence/
```

---

## 8. Phase B Delta — Current Implementation State (2026-08-13)

The design draft above predates implementation and is superseded by these current-state notes where they differ (KEEP — verified):

- **Login rate limiting:** native `RateLimiter::for('login')` (5/min per email+IP) registered in `AppServiceProvider::boot()` — returns the API 429 envelope and logs `LOGIN_THROTTLED`. (Do **not** register rate limiters inside `bootstrap/app.php` `withMiddleware(...)` — the closure runs before the cache provider and fatals.)
- **Audit trail:** `App\Services\AuditLogger` writes `audit_logs` rows with `previous_state`/`new_state` JSON; events include auth (`USER_LOGIN`, `USER_LOGOUT`, `LOGIN_FAILED`, `LOGIN_THROTTLED`), profile updates, product CRUD. `audit_logs.entity_type`/`entity_id` nullable for system/identity events.
- **Business identifiers:** `ORD-`, `BAST-`, `INV-`, `RFQ-` numbers generated through `App\Services\UniqueIdentifier` (retry loop against unique column).
- **Pagination:** listing endpoints clamp `per_page` to 1..100 (default 15) via `Controller::perPage()`.
- **Seeder safety:** `UserSeeder` fails closed in production (`SEED_DEMO_USERS` + named password env vars required).
- **Scheduler:** overdue-invoice check runs daily with `->withoutOverlapping()` and row locks.
- **Schema deltas:** `products.description` nullable; `audit_logs` gained `(entity_type, action, created_at)` / `(action, created_at)` / `(created_at)` indexes; migration `down()` methods for the rfq_id unique index and the order-workflow enum change fixed and rollback-verified with data on MySQL 8.4.3.
- **Order snapshots (current):** `order_items` freeze `ppn_rate_snapshot`/`pph_rate_snapshot` + `product_sku_snapshot`/`product_title_snapshot` at order time (migration `2026_08_16_000001_add_commercial_snapshot_to_order_items.php`). **MODIFY (design):** snapshot akan menjadi `CommercialTaxContext` (base amount, classifications, rule_version) dan `RuleSnapshot` akan menggantikan peran rate sebagai basis penghitungan pajak otoritatif (PRD §7); legacy snapshot dipertahankan untuk kompatibilitas historis — lihat `database_schema.md`.
- **Tax calculation (current):** BAST sign → `COMPLETED` + invoice; PPN dihitung `subtotal × rate ÷ 100` dari snapshot; `grand_total = subtotal + PPN`; PPh dipotong terpisah (tidak masuk grand total). **MODIFY (design):** setelah TaxRule engine tersedia, kalkulasi pajak dialihkan ke `AuthoritativeTaxResolution` (`DPP = Base Amount × 11/12`, `Tax = DPP × Statutory Rate`) dengan `RuleSnapshot`; tidak ada rate hardcoded.
