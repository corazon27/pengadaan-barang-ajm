# Database Schema Specification

**Version:** Rev 3 (additive; correction pass; derived from `docs/PRD.md` Rev 3 + `docs/architecture.md` Rev 3 + `REGULATORY_RULEBOOK.md` v1.0)
**Database Target:** MySQL 8.4 (Laragon)
**Evolution principle:** **Additive/safe evolution only.** Legacy fields preserved for historical compatibility. Module 9 tables below are **DESIGN — NOT IMPLEMENTED** (no migrations, no code). No unresolved legal interpretation is made a mandatory schema field.

**Field markers used throughout:**
| Marker | Meaning |
|---|---|
| `LEGACY` | Kolom lama yang dipertahankan untuk kompatibilitas historis; tidak otoritatif untuk transaksi baru |
| `DEPRECATED` | Jangan dipakai sebagai basis logika baru |
| `SOURCE OF TRUTH` | Kolom/entitas yang menjadi sumber kebenaran |
| `SNAPSHOT` | Salinan beku pada saat kejadian (immutable untuk transaksi historis) |
| `DERIVED` | Dihitung dari kolom lain; tidak disimpan sebagai sumber primer |
| `REFERENCE` | Referensi eksternal/pasif; bukan bukti kepatuhan |

---

## 1. TABLES DEFINITION

### A. USERS & ORGANIZATIONS

```sql
CREATE TABLE users (
  id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  email VARCHAR(255) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(100) NOT NULL,
  role ENUM('SUPERADMIN','BUYER_B2B','BUYER_B2G') NOT NULL DEFAULT 'BUYER_B2B',  -- SOURCE OF TRUTH: organizational role
  company_name VARCHAR(200) NOT NULL,
  npwp_number VARCHAR(30),                    -- SOURCE OF TRUTH: counterparty identity (TAX-PPN-05)
  nik VARCHAR(30) NULL,                       -- DESIGN (additive): NIK per PER-11/PJ/2025; nullable
  address TEXT NOT NULL,
  phone_number VARCHAR(20) NOT NULL,
  tos_accepted_at TIMESTAMP NULL,             -- DESIGN: B2B-05 ToS acceptance gate (AJM policy)
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
-- NOTA: legal functions (DPO, reviewer, tax officer) TIDAK diletakkan di kolom `role`.
-- Legal function = catatan penunjukan terpisah (legal_function_assignments) — lihat §1.M.
```

### B. PRODUCTS

```sql
CREATE TABLE products (
  id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  sku VARCHAR(50) UNIQUE NOT NULL,
  title VARCHAR(255) NOT NULL,
  slug VARCHAR(255) UNIQUE NOT NULL,
  description TEXT NULL,
  base_price NUMERIC(15, 2) NOT NULL CHECK (base_price >= 0),          -- SOURCE OF TRUTH: Harga Jual (dasar DPP)
  margin_percentage NUMERIC(5, 2) DEFAULT 0.00 CHECK (margin_percentage >= 0),
  tax_rate_percentage NUMERIC(5, 2) DEFAULT 11.00 CHECK (tax_rate_percentage >= 0),  -- LEGACY/DEPRECATED: lihat §TAX-DB-RULE
  estimated_shipping NUMERIC(15, 2) DEFAULT 0.00 CHECK (estimated_shipping >= 0),
  tkdn_percentage NUMERIC(5, 2) CHECK (tkdn_percentage >= 0 AND tkdn_percentage <= 100),  -- DERIVED (display only, dari bukti sertifikat; TKDN-05)
  is_sni BOOLEAN DEFAULT FALSE,               -- DEPRECATED (display flag); bukti = product_certifications (PROH-03)
  warranty_info VARCHAR(100),
  datasheet_url VARCHAR(500),
  stock INTEGER DEFAULT 0 CHECK (stock >= 0),
  -- E-catalog status (DESIGN, additive — LKPP-05/06, PROH-02):
  lkpp_product_url VARCHAR(500) NULL,          -- REFERENCE (pasif; BUKAN bukti katalog)
  e_catalog_status ENUM('NOT_LISTED','LISTED','VERIFIED','EXPIRED') NULL,  -- DESIGN: status listing katalog
  e_catalog_verified_at TIMESTAMP NULL,        -- DESIGN
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### C. REQUEST FOR QUOTATIONS (RFQs)

```sql
CREATE TABLE rfqs (
  id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  rfq_number VARCHAR(50) UNIQUE NOT NULL,
  user_id CHAR(36) NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
  status ENUM('SUBMITTED','REVIEWED','APPROVED','REJECTED','CONVERTED_TO_ORDER','QUOTED','CANCELLED') DEFAULT 'SUBMITTED',  -- SOURCE OF TRUTH (deployed enum)
  quotation_pdf_url VARCHAR(500),              -- REFERENCE
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE rfq_items (
  id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  rfq_id CHAR(36) NOT NULL REFERENCES rfqs(id) ON DELETE CASCADE,
  product_id CHAR(36) NOT NULL REFERENCES products(id) ON DELETE RESTRICT,
  quantity INTEGER NOT NULL CHECK (quantity > 0),
  negotiated_price NUMERIC(15, 2) CHECK (negotiated_price >= 0)
);
```

### D. ORDERS & ORDER ITEMS

```sql
CREATE TABLE orders (
  id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  order_number VARCHAR(50) UNIQUE NOT NULL,
  user_id CHAR(36) NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
  rfq_id CHAR(36) REFERENCES rfqs(id) ON DELETE SET NULL,
  -- Canonical order state (deployed enum — TIDAK DIUBAH; keputusan desain #3):
  status ENUM('PENDING_PAYMENT','PROCESSING','SHIPPED','DELIVERED','COMPLETED','CANCELLED') NOT NULL DEFAULT 'PENDING_PAYMENT',  -- SOURCE OF TRUTH: internal order status
  top_days INTEGER DEFAULT 30 CHECK (top_days >= 0),
  total_amount NUMERIC(15, 2) NOT NULL CHECK (total_amount >= 0),
  po_document_url VARCHAR(500),                -- REFERENCE
  -- Procurement channel metadata (DESIGN, additive — GOV-03, LKPP-01/03):
  channel ENUM('B2B_DIRECT','B2G_MIRROR') NULL,                    -- DESIGN
  procurement_method ENUM('TENDER','DIRECT_PROCUREMENT','E_PURCHASING','SELECTION','UNSPECIFIED') NULL,  -- DESIGN; NULL = UNSPECIFIED
  -- External procurement metadata (DESIGN, additive — GOV-04; NOT source of truth internal):
  external_procurement_reference VARCHAR(100) NULL,                -- DESIGN: ref eksternal (SPPBJ/kontrak LKPP)
  external_procurement_status VARCHAR(100) NULL,                   -- DESIGN: status eksternal (informasional)
  external_system VARCHAR(50) NULL,                                -- DESIGN: E_KATALOG / INAPROC / SIRUP / other
  external_status_verified_at TIMESTAMP NULL,                      -- DESIGN
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE order_items (
  id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  order_id CHAR(36) NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
  product_id CHAR(36) NOT NULL REFERENCES products(id) ON DELETE RESTRICT,
  quantity INTEGER NOT NULL CHECK (quantity > 0),
  unit_price NUMERIC(15, 2) NOT NULL CHECK (unit_price >= 0),       -- SOURCE OF TRUTH: harga per unit (dasar Harga Jual)
  subtotal NUMERIC(15, 2) NOT NULL CHECK (subtotal >= 0),
  -- LEGACY snapshots (frozen at order time; dipertahankan — lihat migration add_commercial_snapshot):
  product_sku_snapshot VARCHAR(50) NULL,        -- SNAPSHOT (legacy)
  product_title_snapshot VARCHAR(255) NULL,     -- SNAPSHOT (legacy)
  ppn_rate_snapshot NUMERIC(5,2) NULL,          -- SNAPSHOT (legacy; NOT authoritative — see TAX-DB-RULE)
  pph_rate_snapshot NUMERIC(5,2) NULL,          -- SNAPSHOT (legacy; informational only)
  -- Commercial Tax Context (DESIGN, additive — Tahap 1 model dua-tahap; provisional/non-authoritative):
  -- BASE AMOUNT CLARITY: unit_price_snapshot = Harga Jual per unit; line_base_amount_snapshot = unit_price_snapshot x quantity (line total; dasar DPP per line).
  -- DPP dihitung per line dari line_base_amount_snapshot -> tidak ambigu utk quantity > 1. Total transaksi = SUM(line_base_amount_snapshot) (bukan field terpisah).
  unit_price_snapshot NUMERIC(15,2) NULL,                            -- DESIGN SNAPSHOT: Harga Jual per unit @ order
  line_base_amount_snapshot NUMERIC(15,2) NULL,                      -- DESIGN SNAPSHOT: unit_price_snapshot x quantity (line total; input DPP)
  product_classification_snapshot VARCHAR(100) NULL,                -- DESIGN SNAPSHOT
  buyer_classification_snapshot VARCHAR(100) NULL,                  -- DESIGN SNAPSHOT: regular/government/BUMN/collector
  collector_status_snapshot ENUM('VERIFIED','UNVERIFIED','NOT_APPLICABLE') NULL,  -- DESIGN SNAPSHOT: nullable (TAX-PPN-04)
  transaction_type_snapshot VARCHAR(100) NULL,                      -- DESIGN SNAPSHOT
  order_time_rule_version VARCHAR(50) NULL                          -- DESIGN SNAPSHOT: rule_version @ order date (pricing basis)
);
-- NOTA: status kolektor nullable — tidak membuat interpretasi legal menjadi field wajib.
```

### E. BERITA ACARA SERAH TERIMA (BAST)

```sql
CREATE TABLE bast_documents (
  id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  order_id CHAR(36) NOT NULL REFERENCES orders(id) ON DELETE RESTRICT,
  bast_number VARCHAR(50) UNIQUE NOT NULL,
  bast_document_url VARCHAR(500) NOT NULL,       -- REFERENCE (unggahan scan bertanda tangan)
  signed_date DATE NOT NULL,                     -- SOURCE OF TRUTH: anchor jatuh tempo TOP
  status ENUM('PENDING_SIGNATURE','SIGNED') DEFAULT 'PENDING_SIGNATURE',  -- SOURCE OF TRUTH (deployed)
  signed_by CHAR(36) NULL REFERENCES users(id) ON DELETE SET NULL,
  signed_at TIMESTAMP NULL,
  notes TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### F. INVOICES & TAXES

```sql
CREATE TABLE invoices (
  id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  order_id CHAR(36) NOT NULL REFERENCES orders(id) ON DELETE RESTRICT,
  bast_id CHAR(36) NOT NULL REFERENCES bast_documents(id) ON DELETE RESTRICT,
  invoice_number VARCHAR(50) UNIQUE NOT NULL,
  faktur_pajak_number VARCHAR(50) NULL,           -- REFERENCE
  invoice_pdf_url VARCHAR(500) NOT NULL,          -- REFERENCE
  faktur_pajak_url VARCHAR(500) NULL,             -- REFERENCE
  amount_due NUMERIC(15, 2) NOT NULL CHECK (amount_due >= 0),
  -- Breakdown (deployed; additive columns — migration add_order_workflow_columns):
  subtotal NUMERIC(15, 2) NULL CHECK (subtotal >= 0),
  tax_amount NUMERIC(15, 2) NULL CHECK (tax_amount >= 0),     -- akan menjadi DERIVED dari RuleSnapshot (design)
  grand_total NUMERIC(15, 2) NULL CHECK (grand_total >= 0),
  issued_date DATE NOT NULL,
  due_date DATE NOT NULL,                          -- DERIVED: signed_date + top_days (disimpan untuk performa)
  status ENUM('UNPAID','OVERDUE','PAID','PARTIALLY_PAID') DEFAULT 'UNPAID',  -- SOURCE OF TRUTH: payment state (domain invoice)
  paid_at TIMESTAMP NULL,
  -- Authoritative tax resolution (DESIGN, additive — Tahap 2 model dua-tahap):
  -- KARDINALITAS: satu invoice dapat memuat BANYAK RuleSnapshot (per line item) via join invoice_rule_snapshots;
  -- tidak diasumsikan 1 invoice = 1 tax rule (lihat rule_snapshots + invoice_rule_snapshots di §2.H).
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### G. AUDIT LOGS

```sql
CREATE TABLE audit_logs (
  id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  user_id CHAR(36) REFERENCES users(id) ON DELETE SET NULL,
  action VARCHAR(100) NOT NULL,
  entity_type VARCHAR(50) NULL,        -- nullable: system/identity events (login, logout, throttled)
  entity_id CHAR(36) NULL,
  previous_state JSON,
  new_state JSON,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
-- PSE-AUDIT-001: audit trail ini dipetakan ke PP 71/2019 Ps 22(1) via `audit_trail_mapping` (DESIGN, §1.L).
```

---

## 2. MODULE 9 TABLES (DESIGN — NOT IMPLEMENTED)

Semua tabel berikut adalah **design input** (PRD §9, architecture §3, §24.2). **Tidak ada migrasi/tabel dibuat pada revisi ini.** Tidak ada interpretasi legal yang belum pasti yang dijadikan field wajib (menggunakan NULL/status).

### H. TAX RULES & SNAPSHOTS (Submodule 9.1)

```sql
-- SOURCE OF TRUTH untuk logika pajak (PRD §7; TAX-PPN-01/02/04/05, TAX-PPH-01/02). Versioned, source-cited.
CREATE TABLE tax_rules (                          -- DESIGN
  id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  rule_code VARCHAR(50) NOT NULL,                 -- mis. TAX-PPN-01
  rule_version VARCHAR(50) NOT NULL,              -- versioning (effective-dated)
  tax_type ENUM('PPN','PPh','PPNBM','BEA_METERAI') NOT NULL,
  taxpayer_status VARCHAR(50) NULL,               -- PKP reference; UNVERIFIED if precondition unresolved
  buyer_classification VARCHAR(100) NULL,         -- regular/government/BUMN/designated collector
  vat_collector_status ENUM('VERIFIED','UNVERIFIED','NOT_APPLICABLE') NULL,  -- nullable (TAX-PPN-04)
  transaction_type VARCHAR(100) NULL,
  product_classification VARCHAR(100) NULL,
  base_amount_definition VARCHAR(100) NULL,       -- mis. 'HARGA_JUAL'
  dpp_method ENUM('NILAI_LAIN','HARGA_JUAL','LAINNYA') NOT NULL,
  dpp_formula TEXT NULL,                          -- mis. 'Base Amount x 11/12' (11/12 bagian dari DPP, bukan multiplier post-DPP); Base Amount = line_base_amount_snapshot
  statutory_rate NUMERIC(8,4) NULL,               -- nullable: representasi data, bukan hardcode; mis. 12.0
  tax_formula TEXT NULL,                          -- mis. 'DPP x Statutory Rate'
  effective_burden NUMERIC(8,4) NULL,             -- nullable
  faktur_code VARCHAR(2) NULL,                    -- RESOLVED: mengacu code dari faktur_codes (reference catalog; TAX-PPN-02). Bukan representasi otoritatif kedua.
  withholding_rule TEXT NULL,                     -- nullable: PPh model unresolved (TAX-PPH-01/02 -> REQUIRES_REVIEW)
  legal_reference VARCHAR(255) NOT NULL,          -- source-cited
  effective_from DATE NOT NULL,
  effective_until DATE NULL,
  source_version VARCHAR(50) NULL,
  verification_date DATE NULL,
  applicability ENUM('CONFIRMED','REVIEW_REQUIRED','UNRESOLVED','PENDING_LEGAL_REVIEW','APPLICABILITY_UNKNOWN') NOT NULL DEFAULT 'UNRESOLVED',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE (rule_code, rule_version)
);

-- FAKTUR CODES = REFERENCE/RULE CATALOG (source of truth kanonik utk kode faktur; TAX-PPN-02): 01 default; 02 = pemerintah pemungut; 03 = designated collector.
-- TaxRule (tax_rules.faktur_code) mereferensikan code dari katalog ini; RuleSnapshot (rule_snapshots.faktur_code) = snapshot transaksi immutabel.
-- Tidak ada dua representasi otoritatif dari rule kode yang sama.
CREATE TABLE faktur_codes (                       -- DESIGN
  id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  code VARCHAR(2) UNIQUE NOT NULL,
  description VARCHAR(255) NOT NULL,
  required_buyer_class VARCHAR(100) NULL,         -- eligibilitas buyer
  required_collector_status ENUM('VERIFIED','UNVERIFIED','NOT_APPLICABLE') NULL,
  effective_from DATE NOT NULL,
  effective_until DATE NULL
);
-- TAX-PPN-06 (SPT Tahunan PPN): NOT APPLICABLE — tidak ada fitur/tabel (regression/negative requirement; DB = N/A).

-- Rekaman otoritatif per event pajak (immutable; non-retroaktif; PRD §7, §20).
-- KARDINALITAS (keputusan granularitas): RuleSnapshot dibuat PER LINE ITEM (order_item_id).
-- Satu invoice -> BANYAK RuleSnapshot, diagregasi via invoice_rule_snapshots (tidak diasumsikan 1 invoice = 1 rule).
CREATE TABLE rule_snapshots (                     -- DESIGN
  id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  order_item_id CHAR(36) NULL REFERENCES order_items(id),   -- DESIGN: granularitas line-item (nullable utk snapshot non-line, mis. biaya/diskonto invoice-level)
  tax_rule_id CHAR(36) NOT NULL REFERENCES tax_rules(id),
  rule_version VARCHAR(50) NOT NULL,
  effective_from DATE NOT NULL,
  effective_until DATE NULL,
  tax_type VARCHAR(50) NOT NULL,
  dpp_amount NUMERIC(15,2) NULL,                  -- DPP terselesaikan per line (mis. line_base_amount_snapshot diterapkan formula 11/12)
  statutory_rate_snapshot NUMERIC(8,4) NULL,      -- SNAPSHOT
  tax_formula_snapshot TEXT NULL,                 -- SNAPSHOT
  effective_burden_snapshot NUMERIC(8,4) NULL,    -- SNAPSHOT
  faktur_code VARCHAR(2) NULL,                    -- SNAPSHOT (immutable): hasil resolve dari faktur_codes/tax_rules; bukan representasi otoritatif kedua
  withholding_snapshot TEXT NULL,                 -- SNAPSHOT (nullable; PPh unresolved)
  legal_reference VARCHAR(255) NULL,
  order_time_rule_version VARCHAR(50) NULL,       -- SNAPSHOT: basis pricing @ order (perbedaan order vs event)
  resolution_state ENUM('RESOLVED','REVIEW_REQUIRED','UNRESOLVED','PENDING_LEGAL_REVIEW','APPLICABILITY_UNKNOWN') NOT NULL DEFAULT 'UNRESOLVED',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Join 1 invoice : N RuleSnapshot (per line item).
CREATE TABLE invoice_rule_snapshots (             -- DESIGN — kardinalitas eksplisit (PRD §7, arch §3.2)
  id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  invoice_id CHAR(36) NOT NULL REFERENCES invoices(id),
  rule_snapshot_id CHAR(36) NOT NULL REFERENCES rule_snapshots(id),
  order_item_id CHAR(36) NULL REFERENCES order_items(id),   -- DESIGN: keterkaitan line item
  tax_amount NUMERIC(15,2) NULL,                   -- DERIVED: porsi pajak per line item pada invoice
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE (invoice_id, rule_snapshot_id)
);
```

### I. SUPPLIER / PRODUCT ELIGIBILITY (Submodule 9.2)

```sql
CREATE TABLE supplier_eligibility (               -- DESIGN — GOV-02, TAX-PPN-05, PMSE-01/02
  id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  company_id CHAR(36) NOT NULL,                   -- entitas perusahaan (future; saat ini konfigurasi config/company.php)
  nib VARCHAR(50) NULL,
  npwp VARCHAR(30) NULL,
  kbli VARCHAR(20) NULL,
  kswp_status VARCHAR(50) NULL,
  pkp_status ENUM('VERIFIED','UNVERIFIED','NOT_APPLICABLE') NULL,  -- design precondition §24.1 #1 (nullable)
  evidence_document_id CHAR(36) NULL REFERENCES document_evidence(id),
  verification_state ENUM('REVIEW_REQUIRED','UNRESOLVED','PENDING_LEGAL_REVIEW','VERIFIED') NOT NULL DEFAULT 'UNRESOLVED',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE product_certifications (             -- DESIGN — TKDN-01/02/03/05, PROH-03
  id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  product_id CHAR(36) NOT NULL REFERENCES products(id) ON DELETE CASCADE,
  cert_type ENUM('TKDN','SNI','OTHER') NOT NULL,
  certificate_number VARCHAR(100) NULL,
  issuer VARCHAR(200) NULL,
  issued_at DATE NULL,
  expires_at DATE NULL,                            -- TKDN-03 expiry alert
  evidence_document_id CHAR(36) NULL REFERENCES document_evidence(id),
  tkdn_percentage NUMERIC(5,2) NULL,               -- display only dari bukti (TKDN-05; DERIVED)
  verification_state ENUM('REVIEW_REQUIRED','VERIFIED','EXPIRED','UNRESOLVED') NOT NULL DEFAULT 'REVIEW_REQUIRED',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### J. PSE / PDP (Submodule 9.3)

```sql
CREATE TABLE pse_registration (                   -- DESIGN — PSE-REG-001/002/003
  id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  pse_registration_number VARCHAR(100) NULL,
  pse_type VARCHAR(50) DEFAULT 'PRIVAT',
  registered_at DATE NULL,
  maintenance_due_at DATE NULL,
  -- Registration lifecycle status (internally consistent; default adalah anggota enum):
  registration_status ENUM('UNREGISTERED','PENDING','REGISTERED','SUSPENDED','EXPIRED') NOT NULL DEFAULT 'UNREGISTERED',
  -- Applicability / review status (TERPISAH dari lifecycle registration):
  applicability ENUM('CONFIRMED','REVIEW_REQUIRED','UNRESOLVED','PENDING_LEGAL_REVIEW','APPLICABILITY_UNKNOWN') NOT NULL DEFAULT 'UNRESOLVED',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE pse_certificates (                   -- DESIGN — PSE-CERT-001 (Sertifikat Elektronik PSrE; distinct dari TTE perorangan)
  id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  certificate_number VARCHAR(100) NULL,
  psre_provider VARCHAR(200) NULL,                 -- PSrE Indonesia
  issued_at DATE NULL,
  expires_at DATE NULL,
  status ENUM('ACTIVE','EXPIRED','PENDING','REVIEW_REQUIRED') NOT NULL DEFAULT 'REVIEW_REQUIRED',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE data_subject_requests (              -- DESIGN — PDP-PROC-001, PDP-RIGHT-001..009
  id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  subject_type ENUM('PERSON','LEGAL') NOT NULL,
  right_code VARCHAR(30) NOT NULL,                 -- PDP-RIGHT-001..009
  channel VARCHAR(50) NOT NULL,                    -- elektronik/nonelektronik
  deadline_at TIMESTAMP NULL,                      -- DERIVED: per-hak (hanya 005/007 membawa 3x24h; sisanya reasonable time)
  status ENUM('OPEN','IN_PROGRESS','FULFILLED','REJECTED','REVIEW_REQUIRED','EXPIRED') NOT NULL DEFAULT 'OPEN',
  handled_by CHAR(36) NULL REFERENCES users(id) ON DELETE SET NULL,
  decision_notes TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE consent_records (                    -- DESIGN — B2B-01, ETS-03, PDP-RIGHT-005, SEC-07
  id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  subject_user_id CHAR(36) NULL REFERENCES users(id) ON DELETE SET NULL,
  purpose VARCHAR(200) NOT NULL,
  granted_at TIMESTAMP NOT NULL,
  withdrawn_at TIMESTAMP NULL,
  withdrawal_deadline_at TIMESTAMP NULL,           -- DERIVED / CACHED PROJECTION dari statutory_timers.deadline_at (PDP-3X24-001); SOURCE OF TRUTH = statutory_timers.deadline_at
  document_ref VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE statutory_timers (                   -- DESIGN — PDP-3X24-001/002/003, PROH-05/07
  id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  timer_type ENUM('CONSENT_WITHDRAWAL','RESTRICTION_SUSPENSION','BREACH_NOTIFICATION') NOT NULL,
  -- ENFORCEMENT SEMANTICS (per tipe timer, deterministik; BUKAN generic BLOCK untuk semua):
  --   CONSENT_WITHDRAWAL      -> STOP_PROCESSING           (stop/blokir pemrosesan relevan setelah deadline)
  --   RESTRICTION_SUSPENSION  -> SUSPEND_RESTRICT_PROCESSING (suspend/batasi pemrosesan relevan)
  --   BREACH_NOTIFICATION     -> ESCALATION_VIOLATION_AUDIT (eskalasi + state violation/audit + alur notifikasi; BUKAN blokir pemrosesan)
  enforcement ENUM('STOP_PROCESSING','SUSPEND_RESTRICT_PROCESSING','ESCALATION_VIOLATION_AUDIT') NOT NULL,
  ref_type VARCHAR(50) NULL,
  ref_id CHAR(36) NULL,
  started_at TIMESTAMP NOT NULL,
  deadline_at TIMESTAMP NOT NULL,                  -- SOURCE OF TRUTH: started_at + 72 jam (deterministic); field turunan (mis. consent_records.withdrawal_deadline_at) = cached projection
  status ENUM('RUNNING','MET','VIOLATED','ESCALATED','CANCELLED') NOT NULL DEFAULT 'RUNNING',
  violation_state ENUM('AUDIT','REPORTED','ESCALATED') NULL,   -- untuk BREACH_NOTIFICATION: state violation/audit (bukan block state)
  breach_notification_id CHAR(36) NULL,            -- FK -> breach_notifications(id) (di-resolusi saat migrasi; tabel diurutkan per dependensi)
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE incident_register (                  -- DESIGN — INC-PDP-001, INC-PSE-001, INC-PSE-002, SEC-02
  id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  incident_class ENUM('PDP_BREACH','PSE_DISRUPTION','PSE_PDP_FAILURE') NOT NULL,  -- klasifikasi terpisah wajib (distinct rule/deadline/recipient/evidence)
  description TEXT NULL,
  severity VARCHAR(50) NULL,
  detected_at TIMESTAMP NULL,
  notification_deadline_at TIMESTAMP NULL,         -- DERIVED: +3x24h (PDP-3X24-003)
  status ENUM('OPEN','IN_PROGRESS','REPORTED','RESOLVED','REVIEW_REQUIRED') NOT NULL DEFAULT 'OPEN',
  handled_by CHAR(36) NULL REFERENCES users(id) ON DELETE SET NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE breach_notifications (               -- DESIGN — PDP-BREACH-001, PROH-07
  id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  incident_id CHAR(36) NOT NULL REFERENCES incident_register(id),
  recipient VARCHAR(255) NOT NULL,
  sent_at TIMESTAMP NULL,
  deadline_at TIMESTAMP NULL,                      -- DERIVED
  evidence_document_id CHAR(36) NULL REFERENCES document_evidence(id),
  status ENUM('PENDING','SENT','VIOLATED','REVIEW_REQUIRED') NOT NULL DEFAULT 'PENDING',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE dp_roles (                           -- DESIGN — PDP-DPO-001, ROLE-02: PROYEKSI KHUSUS-DPO (bukan source of truth kedua; canonical = legal_function_assignments)
  id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  function_name VARCHAR(100) NOT NULL,             -- mis. 'DPO', 'PRIVACY_OFFICER'
  assigned_user_id CHAR(36) NULL REFERENCES users(id) ON DELETE SET NULL,
  appointment_basis VARCHAR(255) NULL,             -- basis penunjukan
  effective_from DATE NULL,
  effective_until DATE NULL,
  applicability ENUM('REVIEW_REQUIRED','PENDING_LEGAL_REVIEW','APPLICABILITY_UNKNOWN','ACTIVE') NOT NULL DEFAULT 'PENDING_LEGAL_REVIEW',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
-- NOTA: dp_roles = specialized projection/record untuk DPO saja (ROLE-02). Canonical source of truth penunjukan
-- legal function/DPO = legal_function_assignments (function_category=CONDITIONAL_LEGAL). dp_roles tidak boleh
-- menjadi basis keputusan hukum yang independen; nilainya disinkronkan dari legal_function_assignments (PRD §4, arch §3.3).

CREATE TABLE processor_agreements (               -- DESIGN — SEC-06 (third-party processor agreements)
  id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  processor_name VARCHAR(255) NOT NULL,
  agreement_ref VARCHAR(255) NOT NULL,             -- referensi perjanjian pemroses (third-party processor agreements)
  scope VARCHAR(255) NULL,                         -- lingkup pemrosesan data pribadi
  signed_at DATE NULL,
  effective_from DATE NULL,
  effective_until DATE NULL,
  document_evidence_id CHAR(36) NULL REFERENCES document_evidence(id),
  status ENUM('ACTIVE','EXPIRED','TERMINATED','REVIEW_REQUIRED') NOT NULL DEFAULT 'REVIEW_REQUIRED',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
-- SEC-06: perjanjian pemroses data pihak ketiga (PDP) — rekaman governansi; bukan turunan dari users/consent_records.
```

### K. EVIDENCE, RETENTION & TEMPLATES (Submodule 9.4)

```sql
CREATE TABLE document_evidence (                  -- DESIGN — DOC-01/02/03, GOV-01, PROH-01, B2B-02
  id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  transaction_type VARCHAR(50) NOT NULL,           -- RFQ/ORDER/BAST/INVOICE/PAYMENT/EVIDENCE
  transaction_ref VARCHAR(100) NULL,
  document_kind VARCHAR(100) NOT NULL,
  file_url VARCHAR(500) NULL,                      -- REFERENCE
  sha256_hash VARCHAR(64) NULL,                    -- integritas (DOC-02)
  label ENUM('INTERNAL_MIRROR','ORIGINAL_SCAN','OFFICIAL_EXTERNAL') NOT NULL DEFAULT 'INTERNAL_MIRROR',  -- GOV-01, DOC-03
  disclaimer_text VARCHAR(500) NULL,               -- wording = AJM policy
  template_version VARCHAR(50) NULL,               -- DOC-05
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE retention_policies (                 -- DESIGN — DOC-04, PROH-08, §19
  id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  document_type VARCHAR(100) NOT NULL,
  legal_domain VARCHAR(100) NOT NULL,              -- TAX/PDP/CONTRACT/PSE
  purpose VARCHAR(255) NULL,
  retention_years INTEGER NULL,                    -- nullable: NO universal default; unresolved -> REVIEW
  start_event VARCHAR(100) NULL,
  end_event VARCHAR(100) NULL,
  legal_basis VARCHAR(255) NULL,
  applicability ENUM('RESOLVED','REVIEW_REQUIRED','UNRESOLVED','PENDING_LEGAL_REVIEW','APPLICABILITY_UNKNOWN') NOT NULL DEFAULT 'UNRESOLVED',
  confidence VARCHAR(50) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
-- §19: RFQ/order/PO & BAST retention = UNDEFINED — REQUIRES DOCUMENT-SPECIFIC LEGAL RULE (applicability = UNRESOLVED).
```

### L. KERNEL ENTITIES (Shared Compliance Kernel)

```sql
CREATE TABLE regulatory_references (              -- DESIGN — §20, 9.7 (cross-cutting governance; shared kernel, bukan dependency runtime yang memblok submodule lain)
  id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  regulation_id VARCHAR(100) NOT NULL,             -- mis. 'PMK 11/2025 jo PMK 53/2025'
  article VARCHAR(100) NULL,
  official_source VARCHAR(255) NULL,
  effective_from DATE NULL,
  effective_until DATE NULL,
  source_version VARCHAR(50) NULL,
  verification_date DATE NULL,
  watch_status ENUM('MONITOR','PENDING','NONE_FOUND','N_A') NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE human_review_cases (                 -- DESIGN — §18, 9.5
  id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  rule_code VARCHAR(50) NOT NULL,
  evidence_context JSON NULL,                      -- full rule context: basis, status, inputs, audit references
  authorized_reviewer_role VARCHAR(100) NULL,      -- ROLE-04 / ROLE-05 / PROFESSIONAL
  decision ENUM('APPROVE','REJECT','ESCALATE','RECORD_UNRESOLVED') NULL,
  decided_by CHAR(36) NULL REFERENCES users(id) ON DELETE SET NULL,
  decided_at TIMESTAMP NULL,
  re_review_at DATE NULL,                          -- DERIVED: tahunan atau saat perubahan regulasi (§20)
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE audit_trail_mapping (                -- DESIGN — PSE-AUDIT-001 (Module 8 -> PP 71/2019 Ps 22(1))
  id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  audit_action VARCHAR(100) NOT NULL,
  statutory_basis VARCHAR(255) NOT NULL,
  retention_policy_id CHAR(36) NULL REFERENCES retention_policies(id),
  mapping_notes VARCHAR(500) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### M. CAPABILITIES & LEGAL FUNCTIONS (PRD §4, ROLE-01..08)

```sql
CREATE TABLE capabilities (                       -- DESIGN — application capability catalog
  id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  code VARCHAR(100) UNIQUE NOT NULL,               -- mis. 'review.decide', 'dsr.process', 'taxrule.manage'
  description VARCHAR(255) NULL
);

CREATE TABLE role_capabilities (                  -- DESIGN — role -> capability mapping
  role VARCHAR(30) NOT NULL,                       -- organizational role (user_role)
  capability_id CHAR(36) NOT NULL REFERENCES capabilities(id),
  PRIMARY KEY (role, capability_id)
);

CREATE TABLE legal_function_assignments (         -- DESIGN — regulatory/legal function + governance/operational assignment records
  id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  function_code VARCHAR(50) NOT NULL,              -- DPO (ROLE-02), PRIVACY_OFFICER (ROLE-03), COMPLIANCE_REVIEWER (ROLE-04), TAX_OFFICER (ROLE-05), AUDITOR (ROLE-06), CONSENT_MANAGER (ROLE-07), BREACH_OWNER (ROLE-08)
  -- KATEGORI: pemisahan wajib antara statutory legal function vs governance/operational assignment (PRD §4):
  function_category ENUM('REGULATORY_LEGAL','CONDITIONAL_LEGAL','GOVERNANCE_OPERATIONAL') NOT NULL,
  assigned_user_id CHAR(36) NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  appointment_basis VARCHAR(255) NULL,             -- SURAT_PENUNJUKAN / PENGANGKATAN / basis legal
  statutory_basis VARCHAR(255) NULL,               -- WAJIB terisi utk REGULATORY_LEGAL / CONDITIONAL_LEGAL (rujukan peraturan); governance = pilihan internal
  effective_from DATE NULL,
  effective_until DATE NULL,
  scope VARCHAR(255) NULL,
  applicability ENUM('REVIEW_REQUIRED','PENDING_LEGAL_REVIEW','APPLICABILITY_UNKNOWN','ACTIVE') NOT NULL DEFAULT 'PENDING_LEGAL_REVIEW',  -- DPO conditional; governance boleh ACTIVE setelah penunjukan
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
-- NOTA: Consent Manager (ROLE-07) & Breach Response Owner (ROLE-08) TIDAK otomatis statutory legal function -> GOVERNANCE_OPERATIONAL
-- kecuali basis statutori eksplisit. Reviewer/tax reviewer/auditor = GOVERNANCE_OPERATIONAL kecuali basis hukum eksplisit.
-- DPO = CONDITIONAL_LEGAL (PDP-DPO-001). Legal function TIDAK diturunkan dari nama peran RBAC; hanya dari assignment record ini (PRD §4).
```

---

## 3. TAX DATABASE RULE (products.tax_rate_percentage)

| Aspek | Definisi |
|---|---|
| **Current legacy meaning** | Rate pajak default pada katalog (`DEFAULT 11.00`); dipakai oleh kode saat ini (`ppn = subtotal × rate ÷ 100` saat BAST sign) dan disalin ke `order_items.ppn_rate_snapshot`. |
| **Transitional compatibility** | Kolom **dipertahankan**; kode lama yang membacanya tetap berfungsi; historis tetap konsisten. |
| **New source of truth** | `tax_rules` + `rule_snapshots` (melalui `AuthoritativeTaxResolution`). `products.tax_rate_percentage` **BUKAN** otoritatif untuk transaksi baru. |
| **Migration strategy** | Kolom ditandai `LEGACY`/`DEPRECATED`; tidak di-drop; transaksi baru melewati TaxRule resolver; nilai lama hanya untuk display/kompatibilitas historis. |
| **Historical snapshot compatibility** | `order_items.ppn_rate_snapshot` (LEGACY SNAPSHOT) dipertahankan untuk pesanan lama; pesanan baru menyimpan `CommercialTaxContext` + `rule_snapshots`. Snapshot lama valid sebagai rekaman historis, bukan basis penghitungan pajak baru. |

**Rule:** existing field must NOT remain authoritative for new transactions — kalkulasi pajak baru wajib melalui `TaxRule`/`RuleSnapshot` (PRD §7).

---

## 4. ORDER STATE RULE

- Enum deployed **tidak diubah** (keputusan desain #3): `PENDING_PAYMENT`, `PROCESSING`, `SHIPPED`, `DELIVERED`, `COMPLETED`, `CANCELLED` — `orders.status` (SOURCE OF TRUTH internal).
- Payment state tetap di domain invoice: `invoices.status` (`UNPAID`/`OVERDUE`/`PAID`/`PARTIALLY_PAID`), `paid_at`.
- Metadata pengadaan eksternal ditambahkan (kolom `external_*`, §1.D) alih-alih membebani `order.status` — GOV-04.
- Legacy `DRAFT`/`WAITING_PO`/`BAST_SIGNED`/`INVOICED`/`PAID` dimapping ke status kanonik (PRD §5) hanya untuk kompatibilitas historis; bukan nilai enum baru.

---

## 5. INDEXES FOR PERFORMANCE OPTIMIZATION

```sql
-- Existing (KEEP):
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_products_sku ON products(sku);
CREATE INDEX idx_products_slug ON products(slug);
CREATE INDEX idx_rfqs_user_id ON rfqs(user_id);
CREATE INDEX idx_rfqs_status ON rfqs(status);
CREATE INDEX idx_orders_user_id ON orders(user_id);
CREATE INDEX idx_orders_status ON orders(status);
CREATE INDEX idx_invoices_order_id ON invoices(order_id);
CREATE INDEX idx_invoices_due_date ON invoices(due_date);
CREATE INDEX idx_invoices_status ON invoices(status);
CREATE INDEX idx_audit_logs_entity ON audit_logs(entity_type, entity_id);
CREATE INDEX idx_audit_logs_created_at ON audit_logs(created_at);
CREATE INDEX idx_audit_logs_entity_action_created ON audit_logs(entity_type, action, created_at);
CREATE INDEX idx_audit_logs_action_created ON audit_logs(action, created_at);

-- DESIGN (additive; Module 9):
CREATE INDEX idx_order_items_order_id ON order_items(order_id);
CREATE INDEX idx_order_items_product_id ON order_items(product_id);
CREATE INDEX idx_tax_rules_effective ON tax_rules(rule_code, effective_from, effective_until);
CREATE INDEX idx_rule_snapshots_order_item ON rule_snapshots(order_item_id);
CREATE INDEX idx_invoice_rule_snapshots_invoice ON invoice_rule_snapshots(invoice_id);
CREATE INDEX idx_invoice_rule_snapshots_snapshot ON invoice_rule_snapshots(rule_snapshot_id);
CREATE INDEX idx_product_certifications_product ON product_certifications(product_id);
CREATE INDEX idx_human_review_cases_rule ON human_review_cases(rule_code, decision);
CREATE INDEX idx_statutory_timers_deadline ON statutory_timers(deadline_at, status);
CREATE INDEX idx_statutory_timers_enforcement ON statutory_timers(enforcement, status);
CREATE INDEX idx_incident_register_class ON incident_register(incident_class, status);
CREATE INDEX idx_data_subject_requests_status ON data_subject_requests(status, deadline_at);
CREATE INDEX idx_document_evidence_txn ON document_evidence(transaction_type, transaction_ref);
CREATE INDEX idx_legal_function_assignments_user ON legal_function_assignments(assigned_user_id);
CREATE INDEX idx_processor_agreements_status ON processor_agreements(status);
```

---

## 6. UPDATED_AT HANDLING

Kolom `updated_at` dikelola otomatis oleh Eloquent (Laravel) melalui model events (`updating`). Tidak diperlukan trigger database.

---

## 7. LEGACY / DEPRECATED / COMPATIBILITY SUMMARY

| Kolom/Entitas | Status | Keterangan |
|---|---|---|
| `products.tax_rate_percentage` | `LEGACY` / `DEPRECATED` (new transactions) | Diganti `tax_rules` + `rule_snapshots`; dipertahankan untuk kompatibilitas |
| `order_items.ppn_rate_snapshot` / `pph_rate_snapshot` | `SNAPSHOT` (legacy) | Dipertahankan; bukan basis pajak baru |
| `order_items.product_sku_snapshot` / `product_title_snapshot` | `SNAPSHOT` (legacy) | Dipertahankan |
| `products.is_sni` / `products.tkdn_percentage` | `DEPRECATED` (display flag / DERIVED) | Bukti = `product_certifications` (PROH-03) |
| `orders.status` legacy values (`DRAFT`, `WAITING_PO`, `BAST_SIGNED`, `INVOICED`, `PAID`) | `LEGACY` | Mapping ke status kanonik (PRD §5); bukan nilai enum baru |
| `lkpp_product_url` | `REFERENCE` | Bukan bukti katalog (LKPP-05) |
| `invoices.status` | `SOURCE OF TRUTH` (payment state) | Domain invoice, terpisah dari order state |
| `users.role` | `SOURCE OF TRUTH` (organizational role) | Legal function via `legal_function_assignments`, bukan `role` |

**Design-corrected fields (Rev 2 → Rev 3; belum pernah diimplementasikan):**
| Kolom/Entitas | Perubahan | Keterangan |
|---|---|---|
| `invoices.rule_snapshot_id` (single FK) | diganti join `invoice_rule_snapshots` | Kardinalitas 1 invoice : N RuleSnapshot (per line item); tidak diasumsikan 1 invoice = 1 rule |
| `order_items.base_amount_snapshot` | diganti `unit_price_snapshot` + `line_base_amount_snapshot` | Ambiguity quantity > 1 dihilangkan; DPP per line dari `line_base_amount_snapshot` |
| `pse_registration.status` | diganti `registration_status` (lifecycle) + `applicability` (review) | Default `UNRESOLVED` tidak lagi invalid (bukan anggota enum) |
| `statutory_timers` | + kolom `enforcement`, `violation_state` | Enforcement per tipe timer (stop/suspend/eskalasi); bukan generic BLOCK |

*End of Database Schema Specification Rev 3. Additive only. Module 9 entities are design inputs — not implemented.*
