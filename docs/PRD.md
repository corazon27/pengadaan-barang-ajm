# Product Requirements Document (PRD)

**Project:** Standalone B2B & B2G E-Procurement Platform (single-supplier)
**Status:** Authoritative product specification (derived from `REGULATORY_RULEBOOK.md` v1.0, approved Specification Reconciliation Proposal, the four approved final design decisions, and the approved final specification correction pass)
**Date Updated:** 14 Agustus 2026 (Rev 3 — correction pass)
**Source hierarchy:** `REGULATORY_RULEBOOK.md` → `REGULATORY_COMPLIANCE_AUDIT.md` → PRD ini → `architecture.md` → `database_schema.md`

---

## 1. Visi Produk

Membangun platform katalog produk dan e-commerce mandiri (sebagai *single-supplier*) yang dikhususkan untuk pengadaan barang bagi instansi pemerintah (B2G) dan perusahaan swasta (B2B). Sistem dirancang untuk mengakomodasi siklus pengadaan yang kompleks, termasuk *Term of Payment* (TOP) atau dana talangan, dengan mematuhi regulasi hukum pengadaan di Indonesia sesuai Rulebook.

**Prinsip non-negosiasi:**
1. Sistem ini adalah **alat pendukung sisi penjual (supplier-side support tool)** dan **cermin dokumen internal (internal mirror)**. Sistem **tidak** mengoperasikan sistem pengadaan resmi pemerintah (SIKAP/e-Procurement/e-Katalog adalah milik LKPP/KPB) — GOV-01, GOV-04, GOV-05.
2. Tidak ada `ORD-*` atau dokumen platform yang dipresentasikan sebagai instrumen pengadaan resmi — PROH-01.
3. Tidak ada tawaran non-katalog yang dipresentasikan sebagai tawaran e-Katalog — LKPP-06, PROH-02.
4. Tidak ada klaim TKDN/SNI tanpa bukti — PROH-03.
5. Tidak ada kesimpulan hukum yang belum pasti yang dikonversi menjadi logika deterministik — §17 Rulebook.

---

## 2. Kepatuhan Hukum & Regulasi (Compliance)

*   **Pendaftaran PSE:** Sistem harus memenuhi standar untuk didaftarkan sebagai Penyelenggara Sistem Elektronik (PSE) Kominfo Lingkup Privat — PSE-REG-001/002/003, PSE-CERT-001 (sertifikat elektronik via PSrE Indonesia, distinct dari TTE perorangan — §8).
*   **Dukungan Pengadaan B2G (Pemerintah):** Sistem tidak memproses pembayaran langsung dari APBN/APBD. Untuk klien pemerintah, AJM adalah **alat pendukung sisi penjual (supplier-side support)**, **handoff**, dan **referensi** ke kanal pengadaan resmi yang berlaku sesuai aturan/metode (mis. e-Katalog/e-Purchasing LKPP sesuai aturan dan pengecualiannya, INAPROC, SIRUP, dsb.) — AJM tidak mengoperasikan sistem pengadaan resmi pemerintah; **e-Katalog tidak diasumsikan sebagai metode B2G universal** — GOV-01/04, LKPP-01..06.
*   **Legalitas Perusahaan:** Sistem mensyaratkan dan **merekam bukti** (bukan sekadar deklarasi) untuk NIB, NPWP, KBLI, status PKP (untuk penerbitan e-Faktur), dan KSWP — GOV-02, TAX-PPN-05. Status PKP adalah **design precondition** (§10, §24.1 #1) — sistem menyimpan status dan menandai `UNVERIFIED` hingga diverifikasi.
*   **Transaksi Elektronik:** Kontrak B2B yang terbentuk melalui platform adalah transaksi elektronik (UU ITE Ps 5–6, 18–19); setiap transaksi merekam peristiwa persetujuan/konsen dengan timestamp dan identitas — B2B-01, ETS-01/03, B2B-05 (ToS gate adalah kebijakan AJM, bukan blocker statutori).
*   **PDP/Pelindungan Data Pribadi:** Sistem mengimplementasikan hak subjek data (PDP-RIGHT-001..009), prosedur permohonan (PDP-PROC-001), timer statutori 3×24 jam (PDP-3X24-001/002/003), kewajiban notifikasi pelanggaran (PDP-BREACH-001), dan kewajiban controller/processor (PDP-DPO-002). **Trigger DPO (PDP-DPO-001/ROLE-02) tetap `PENDING_LEGAL_REVIEW`** hingga applicability diputuskan oleh professional review — tidak boleh diasumsikan deterministik.
*   **Pajak:** Model pajak dua-tahap (lihat §7). Tidak ada rate pajak yang di-hardcode; semua aturan pajak direpresentasikan melalui `TaxRule` engine yang berversi dan bersumber (source-cited) — TAX-PPN-01..05, TAX-PPH-01/02. Item dengan basis hukum belum pasti (PPh exact model, B2G collector scope) tetap `UNRESOLVED` / `REQUIRES_PROFESSIONAL_REVIEW`.

---

## 3. B2G Boundary & External Procurement Channel Model

**Batasan B2G (GOV-01, GOV-04, GOV-05, PROH-01, LKPP-06):**

**Prinsip dukungan B2G:** AJM menyediakan **dukungan sisi penjual (supplier-side support)**, **handoff**, dan **referensi** ke kanal pengadaan resmi yang berlaku sesuai aturan. **e-Katalog tidak diasumsikan sebagai metode B2G universal**; e-Purchasing/e-Catalog diperlakukan sesuai aturan dan pengecualiannya (per metode pengadaan yang berlaku — tender, direct procurement, selection, e-purchasing).

| Konsep | Sistem AJM (internal) | Sistem Pemerintah (eksternal) |
|---|---|---|
| Status | Status internal pesanan (kanonik §5) | Status pengadaan resmi (award/selection) |
| Dokumen | Cermin internal (`ORD-*`, penawaran, BAST mirror) | Instrumen resmi (PO resmi, SPPBJ, SPK) |
| Referensi katalog | `lkpp_product_url` (REFERENCE, bukan bukti) | Rekaman katalog resmi di kanal pengadaan resmi yang berlaku (mis. e-Katalog/e-Purchasing LKPP sesuai aturan dan pengecualiannya) |
| Channel | RFQ/negosiasi/penawaran | Kanal resmi yang berlaku sesuai aturan/metode (e-Purchasing, INAPROC, SIRUP, dsb.) — bukan asumsi universal |

**External procurement metadata (external, bukan source of truth internal):**
Setiap pesanan B2G dapat membawa referensi ke sistem pengadaan eksternal:
- `external_procurement_reference` — nomor/nomor referensi pengadaan eksternal (mis. nomor SPPBJ, nomor kontrak LKPP).
- `external_procurement_status` — status di sistem eksternal (informasional saja).
- `external_system` — identifikasi sistem eksternal (mis. `E_KATALOG`, `INAPROC`, `SIRUP`).
- `external_status_verified_at` — timestamp kapan status eksternal terverifikasi.

**Aturan:**
- `external_procurement_status` **tidak pernah** ditulis ke `orders.status`. Status internal tetap kanonik (§5). — GOV-04.
- `lkpp_product_url` adalah referensi pasif; kehadirannya **bukan bukti** kelayakan katalog (LKPP-05). Bukti katalog diwakili status katalog terverifikasi (lihat §8 `ECatalogStatus`).
- Pesanan B2G yang mencerminkan transaksi eksternal boleh mencapai terminal state internal (`COMPLETED`) sementara progres resmi dicatat hanya di field eksternal.

**Procurement-method/channel awareness (GOV-03, LKPP-01/02/03):**
- Sistem mendukung beberapa metode pengadaan (tender, direct procurement, e-purchasing, selection) melalui abstraksi channel, bukan asumsi satu metode.
- Jika produk tercatat di e-Katalog, sistem **memperingatkan** bahwa e-purchasing **dapat berlaku** sesuai aturan dan pengecualiannya; sistem **tidak** auto-selecting channel — butuh penilaian PPK (human review) — LKPP-01.
- Kategori produk yang belum tercatat di katalog dimonitor melalui **category gap matrix** (bukan % coverage sebagai metrik kepatuhan) — LKPP-02.

---

## 4. Personas, Capabilities & Legal Functions

Empat konsep **harus dipisahkan** (keputusan desain #2; ROLE-01..08):

| Konsep | Definisi | Contoh |
|---|---|---|
| **Organizational Role** | Posisi manusia/organisasi; wadah penugasan, bukan izin | `SUPERADMIN`, `BUYER_B2B`, `BUYER_B2G` |
| **Application Capability** | Izin operasi tingkat sistem yang ditegakkan policy | `product.manage`, `payment.verify`, `invoice.issue`, `order.cancel`, `audit_log.view`, `review.decide`, `dsr.process`, `consent.manage`, `incident.respond`, `retention.manage`, `taxrule.manage`, `labeling.override` |
| **Regulatory/Legal Function** | Kewajiban hukum/statutori yang harus diemban orang tertentu; **catatan penunjukan**, bukan nama peran; valid **hanya jika basis statutori eksplisit** terdokumentasi | DPO/PDP function officer (ROLE-02, **kondisional**), Privacy Officer (ROLE-03, bila diwajibkan peraturan) |
| **Internal Governance / Operational Assignment** | Penugasan tata kelola/operasional internal; **bukan** kewajiban statutori otomatis; tidak boleh diklasifikasikan sebagai legal function tanpa basis hukum eksplisit | Compliance/Legal Reviewer (ROLE-04), Tax Reviewer (ROLE-05), Auditor (ROLE-06), Consent Manager (ROLE-07), Breach Response Owner (ROLE-08) |

**Aturan anti-inferensi (§14, §17, §18):**
- Nama peran RBAC (`SUPERADMIN`, `Auditor`) hanyalah namespace capability — **tidak membuktikan** pemegang legal function.
- Seorang `SUPERADMIN` **bukan** DPO kecuali ditunjuk dan dicatat di `legal_function_assignments`.
- Capability mengontrol **aksi**; legal function mengontrol **attestasi keputusan** (mis. siapa yang menyetujui kasus `REQUIRE_REVIEW`).
- **Tidak otomatis** mengklasifikasikan Consent Manager (ROLE-07) atau Breach Response Owner (ROLE-08) sebagai statutory legal function; keduanya adalah **internal governance/operational assignment**, kecuali ada ketentuan peraturan yang secara eksplisit menuntut fungsi tersebut.
- Reviewer (ROLE-04), tax reviewer (ROLE-05), dan auditor (ROLE-06) adalah **governance assignments** (catatan penunjukan internal); diangkat ke legal function **hanya jika** basis statutori eksplisit terdokumentasi.
- Evaluasi item spesifik:
  - `SUPERADMIN` → organizational role dengan banyak capabilities; tanpa legal function bawaan (ROLE-01).
  - reviewer → governance assignment ROLE-04 + capability `review.decide` (menyetujui template/channel/kasus); legal function hanya jika basis statutori terdokumentasi.
  - tax reviewer → governance assignment ROLE-05 + capability `tax.review` (faktur/withholding; TAX-PPN-03/04); legal function hanya jika basis statutori terdokumentasi.
  - auditor → governance assignment ROLE-06 + capability `audit_log.view` (kewajiban audit trail = PSE-AUDIT-001; peran auditor = pilihan tata kelola kecuali basis statutori eksplisit).
  - DPO/PDP function → **conditional legal function** ROLE-02; trigger **kondisional** dan `PENDING_LEGAL_REVIEW` (PDP-DPO-001); dicatat di `legal_function_assignments` (`function_category = CONDITIONAL_LEGAL`) — **canonical source of truth**; `dp_roles` (jika dipertahankan) hanyalah **proyeksi khusus-DPO**, bukan source of truth kedua.
  - consent capability → governance assignment ROLE-07 + capability `consent.manage` (operator rekaman konsen); bukan legal function default.
  - breach-response capability → governance assignment ROLE-08 + capability `incident.respond` + pemilik tanggap-insiden; bukan legal function default.

---

## 5. Canonical Order State Machine

**Status internal kanonik (enum deployed, tidak diubah — keputusan desain #3):**

```
PENDING_PAYMENT → PROCESSING → SHIPPED → DELIVERED → COMPLETED
                                                   ↘ CANCELLED (terminal)
```

| Status | Makna internal |
|---|---|
| `PENDING_PAYMENT` | Pesanan dibuat/dikonversi dari RFQ; menunggu konfirmasi buyer & PO (menampung makna legacy `DRAFT` + `WAITING_PO`) |
| `PROCESSING` | Superadmin menyorot PO dan memproses pemenuhan (fase dana talangan) |
| `SHIPPED` | Barang dalam pengiriman |
| `DELIVERED` | Barang tiba di lokasi |
| `COMPLETED` | BAST ditandatangani + Invoice diterbitkan (terminal; pembayaran dilacak di invoice) |
| `CANCELLED` | Terminal |

**Prinsip:**
- **Status pembayaran (payment state) tetap di domain invoice/payment**: `invoices.status` (`UNPAID`/`OVERDUE`/`PAID`/`PARTIALLY_PAID`), `paid_at`. `PAID` **bukan** status order — kompatibel dengan implementasi saat ini (BAST sign → order `COMPLETED` + invoice `UNPAID`; rekonsiliasi pembayaran menggerakkan status invoice).
- `BAST_SIGNED` dan `INVOICED` legacy direpresentasikan sebagai sub-peristiwa: `bast_documents.status` (`PENDING_SIGNATURE`/`SIGNED`) dan `invoices.*`; bukan status order.
- **Status internal ≠ status pengadaan resmi** — GOV-04. Metadata eksternal (§3) menyimpan status eksternal.
- **Mapping legacy (PRD lama / arsitektur draft / enum DB lama → kanonik):**

| Legacy | Kanonik |
|---|---|
| `DRAFT` | `PENDING_PAYMENT` |
| `WAITING_PO` | `PENDING_PAYMENT` |
| `PROCESSING` | `PROCESSING` |
| `SHIPPED` | `SHIPPED` |
| `BAST_SIGNED` | `COMPLETED` (BAST signed + invoice issued) |
| `INVOICED` | `COMPLETED` (invoice issued) |
| `PAID` | `COMPLETED` (order-level) + `invoices.status = PAID` |
| `CANCELLED` | `CANCELLED` |

---

## 6. Alur Kerja Utama (Core Workflows)

Sistem menggunakan alur pemesanan berbasis dokumen dan kredit (TOP), bukan *instant checkout*:

1.  **Request for Quotation (RFQ):** Klien memilih produk dan *submit* permintaan. Sistem menghasilkan dokumen Surat Penawaran Harga (PDF) yang diberi label **cermin internal** — GOV-01, DOC-03.
2.  **Purchase Order (PO):** Klien mengunggah dokumen PO resmi mereka ke dalam sistem sebagai tanda persetujuan (menggerakkan `PENDING_PAYMENT` → konfirmasi). `ORD-*` platform tidak pernah dipresentasikan sebagai PO resmi — PROH-01.
3.  **Order Fulfillment:** Superadmin memproses dan mengirimkan pesanan (`PROCESSING` → `SHIPPED` → `DELIVERED`).
4.  **Berita Acara Serah Terima (BAST):** Fitur *generate* dan *upload* dokumen BAST yang telah ditandatangani kedua belah pihak di lapangan (`DELIVERED` → `COMPLETED`, auto-issue invoice). Tanggal BAST menjadi **anchor** perhitungan jatuh tempo TOP.
5.  **Invoicing & TOP:** Setelah `COMPLETED`, Superadmin menerbitkan Invoice dan e-Faktur. Argo *Term of Payment* (misal: 30, 60, 90 hari) otomatis berjalan dari `signed_date` BAST. **Pajak dihitung pada tahap resolusi otoritatif (lihat §7)**, bukan dari rate katalog.

---

## 7. Model Pajak Dua-Tahap (Tax Model)

**Keputusan desain #1: TWO-STAGE MODEL.** Tidak ada asumsi tunggal bahwa "penerbitan invoice selalu merupakan taxable event" — ketika pemicu legal pasti tidak terselesaikan, status yang berlaku adalah `REVIEW`/`UNRESOLVED`, bukan kesimpulan hukum.

### Tahap 1 — Commercial Tax Context (entitas `CommercialTaxContext`; saat order; provisional / non-authoritative)
- Freeze fakta komersial saat order: `unit_price_snapshot` (**Harga Jual per unit**), `line_base_amount_snapshot` (= `unit_price_snapshot` × kuantitas; **dasar DPP per line item**), klasifikasi produk, klasifikasi buyer, status kolektor (jika terverifikasi; jika belum → flag `UNVERIFIED`), tipe transaksi, referensi status PKP, dan `rule_version` yang berlaku pada tanggal order sebagai **dasar penentuan harga** (pricing basis).
- **Clarifikasi base amount:** DPP dihitung per line dari `line_base_amount_snapshot`, sehingga tidak ambigu untuk kuantitas > 1. Total transaksi = penjumlahan `line_base_amount_snapshot` (bukan field terpisah).
- Output ini bersifat sementara; tidak pernah menjadi angka pajak final.

### Tahap 2 — Authoritative Tax Resolution Event (entitas `AuthoritativeTaxResolution`; saat event pajak; authoritative)
- Menyelesaikan `TaxRule` menggunakan **fakta transaksi yang berlaku** dan **aturan efektif** pada tanggal kejadian.
- Menghasilkan `RuleSnapshot` **per line item** (`order_items`), yang diagregasi ke rekaman invoice/pajak otoritatif melalui join `invoice_rule_snapshots`.
- Jika pemicu legal tax-event **belum terselesaikan**, state yang dipakai adalah `REVIEW_REQUIRED` / `UNRESOLVED` / `PENDING_LEGAL_REVIEW` / `APPLICABILITY_UNKNOWN` — tidak mengarang kesimpulan legal.
- Perbedaan antara basis order dan aturan saat event dicatat (keduanya disimpan); tidak pernah meninjau ulang invoice historis (non-retroaktif, §20).

### TaxRule requirements
- `TaxRule` adalah registry aturan pajak yang **deterministik, berversi, bersumber (source-cited)**. Rate **tidak pernah di-hardcode** (`tax_rate = 11`/`= 12` adalah representasi invalid) — TAX-PPN-01.
- Model konseptual per aturan (dari §10): Tax Type, Taxpayer Status, Buyer Classification, VAT Collector Status, Transaction Type, Product Classification, Base Amount, DPP Method, DPP Formula, Statutory Rate, Tax Formula, Effective Burden, Faktur Pajak Code, Withholding Rule, Effective From/Until, Legal Reference.
- Untuk PPN standar non-luxury saat ini: DPP Method = `NILAI_LAIN`, DPP Formula = `Base Amount × 11/12`, Statutory Rate = `12%`, Tax Formula = `DPP × Statutory Rate`, Effective Burden = `11%` dari Harga Jual. **Faktor 11/12 adalah bagian dari penentuan DPP, bukan multiplier tambahan setelah DPP** — TAX-PPN-01.
- Faktur code dipilih **berdasarkan hierarki** (code 01 default; code 02 hanya pemerintah pemungut; code 03 hanya pemungut yang ditunjuk) — TAX-PPN-02.
- Klasifikasi buyer (regular, government, BUMN, designated collector, dst.) adalah dimensi terpisah dari kode faktur dan kondisi kolektor B2G (TAX-PPN-04).

### RuleSnapshot requirements
- **Kardinalitas (keputusan granularitas):** satu invoice **dapat memuat banyak** `RuleSnapshot`. Snapshot dibuat **per line item** (`order_items`); agregasi ke invoice dilakukan via join `invoice_rule_snapshots`. **Tidak ada asumsi 1 invoice = 1 aturan pajak tunggal.**
- Setiap snapshot peristiwa pajak otoritatif menyimpan: line item yang diselesaikan, rule yang dipakai, versi, rentang efektif, formula DPP, rate statutori, burden efektif, kode faktur, referensi legal.
- Snapshot bersifat immutable untuk transaksi historis; perubahan aturan hanya memengaruhi transaksi dengan tanggal kejadian ≥ `effective_from` (non-retroaktif).

---

## 8. Kebutuhan Fitur (Feature Requirements)

### Katalog & Etalase Produk
*   **Indikator TKDN/SNI berbasis bukti (evidence-gated):** Sistem **menampilkan** TKDN/SNI hanya jika ada bukti sertifikat yang terekam (`product_certifications`), dengan atribut (nomor sertifikat, penerbit, expiry). **Tidak** ada inferensi otomatis persentase (TKDN-05). Klaim tanpa bukti diblokir (PROH-03). Peringatan untuk produk non-sertifikasi yang ditawarkan ke pemerintah (TKDN-04).
*   **E-Catalog status tracking:** Setiap produk dapat membawa status listing katalog (mis. `NOT_LISTED`, `LISTED`, `VERIFIED`, `EXPIRED`) via `e_catalog_status`. `lkpp_product_url` bukan bukti (LKPP-05). Tawaran non-katalog tidak boleh diklaim sebagai e-katalog (LKPP-06, PROH-02).
*   **Transparansi Harga:** Struktur harga jelas (Harga Dasar, Margin, Pajak/PPN **provisional** di tingkat quote, Estimasi Ongkir). Pajak final dihitung pada event resolusi otoritatif (§7).
*   **Spesifikasi Teknis:** Dukungan untuk *upload datasheet* (PDF), status SNI (berbasis bukti), dan informasi garansi.

### Manajemen Order & Piutang
*   **Dashboard Status:** Pelacakan visual pesanan dengan **vocabularies status kanonik** (§5) dan label **internal** yang jelas; label status pengadaan resmi tampil hanya dari metadata eksternal (§3) — GOV-04.
*   **Sistem Pengingat:** Notifikasi jatuh tempo pembayaran (*Due Date Alert*) berdasarkan perhitungan TOP dari `signed_date` BAST.

### PSE/PDP (Module 9.3)
*   **PSE Registration & Certificate:** Rekaman pendaftaran PSE (nomor, tanggal, data pemeliharaan — PSE-REG-001/002/003) dan rekaman Sertifikat Elektronik PSrE (PSE-CERT-001), distinct dari TTE perorangan.
*   **Data Subject Request (DSR):** Alur permintaan hak subjek data (PDP-RIGHT-001..009) melalui prosedur intake (PDP-PROC-001). Deadline per hak: hanya PDP-RIGHT-005 dan PDP-RIGHT-007 yang membawa deadline statutori 3×24 jam.
*   **Consent Records:** Rekaman persetujuan per transaksi (B2B-01, ETS-03) dan untuk pemrosesan (SEC-07); penarikan persetujuan memicu timer 3×24 jam dan **stop/blokir pemrosesan yang relevan setelah deadline** (PDP-3X24-001, PROH-05).
*   **Breach/Incident & Timers:** Rekaman insiden dengan klasifikasi terpisah — PDP breach (INC-PDP-001), gangguan serius sistem (INC-PSE-001), kegagalan PDP dalam PSE (INC-PSE-002) — masing-masing dengan aturan, deadline, penerima, bukti, eskalasi berbeda (PROH-07). Timer 3×24 jam deterministik untuk penarikan persetujuan (→ stop/blokir pemrosesan), pembatasan/pembekuan (→ suspend/batasi pemrosesan), dan notifikasi pelanggaran (→ **eskalasi + state violation/audit + alur notifikasi; bukan blokir pemrosesan**) (PDP-3X24-001/002/003).

### Dokumen, Bukti & Retensi (Module 9.4)
*   **Document Evidence:** Daftar dokumen legal per transaksi (RFQ, order, BAST, invoice, payment) dengan hash integritas dan label mirror/original (DOC-01/02/03, GOV-01, PROH-01). Checklist kelengkapan dokumen (DOC-01).
*   **Retention Policy Engine:** Kebijakan retensi per dokumen berdasarkan §19 (tax 10 tahun untuk invoice/faktur; RFQ/order/PO dan BAST `UNDEFINED — REQUIRES DOCUMENT-SPECIFIC LEGAL RULE`). **Tidak ada** default universal `retention_years`; konflik/belum tuntas → `REQUIRE_REVIEW` (DOC-04, PROH-08).

### Human Review (Module 9.5)
*   Workflow kasus review manusia: sistem menyajikan konteks lengkap (dasar, status, input, referensi audit), reviewer berwenang (ROLE-04/05 atau professional) memutuskan: approve / reject / escalate / record unresolved — §18. Setiap keputusan tercatat dengan timestamp dan identitas reviewer. Re-review tahunan atau saat perubahan regulasi (§20).

### Antarmuka Publik & Edukasi
*   **Halaman Cara Order:** Sistem menyediakan halaman "Cara Order" atau panduan khusus untuk mengedukasi klien baru terkait prosedur pemesanan yang berbeda dari e-commerce ritel biasa.

---

## 9. Module 9 — Compliance Foundation (Parent) + Submodules

**Keputusan desain #4:** Module 9 = parent "Compliance Foundation" dengan **bounded submodules** (bukan satu modul monolitik). Parent menyediakan compliance kernel (RuleSnapshot, RegulatoryReference, HumanReviewCase, capabilities + legal function assignments, dan ledger status 98 aturan). Setiap submodule dibatasi oleh klasifikasi §24.3.

| Submodule | Cakupan | Rule IDs | Klasifikasi §24.3 |
|---|---|---|---|
| **9.1 Tax & Fiscal Rules** | TaxRule engine (DPP, faktur code, PPh flags, B2G collection, E-Faktur/SIAP data) | TAX-PPN-01..05, TAX-PPH-01/02, TAX-CRT-01 (decision record) | MUST (PPN); PPh/B2G collector → **BLOCKED BY LEGAL REVIEW** |
| **9.2 Supplier & Product Eligibility** | Supplier eligibility (NIB/NPWP/KBLI/KSWP), product certifications (TKDN/SNI), e-catalog status | GOV-02, TKDN-01..05, LKPP-05, PMSE-01/02, PROH-03 | MUST (evidence records); applicability → **BLOCKED BY LEGAL REVIEW** |
| **9.3 PSE/PDP Compliance** | PSE registry, certificate, audit-trail mapping, PDP service (DSR, consent, breach, timers, DPO record) | PSE-REG-001..003, PSE-GOV-001..003, PSE-DATA-001/002, PSE-AUDIT-001, PSE-SEC-001, PSE-CERT-001, PDP-RIGHT-001..009, PDP-PROC-001, PDP-BREACH-001, PDP-3X24-001/002/003, PDP-DPO-001/002, ROLE-02/07/08, INC-PSE-001/002, INC-PDP-001, PROH-04/05/06/07 | MUST; DPO trigger → **BLOCKED BY LEGAL REVIEW** |
| **9.4 Evidence & Documents** | Document evidence, labelling interceptor, retention policy engine, template versioning | DOC-01..05, GOV-01, PROH-01/08, B2B-02 | MUST |
| **9.5 Human Review** | HumanReviewCase workflow, reviewer assignment, decision audit, re-review triggers | §18, ROLE-03/04/05 | MUST (shared) |
| **9.6 Procurement Channel Intelligence** | Method abstraction, e-purchasing default warning (sesuai aturan & pengecualiannya), category gap matrix, non-catalog guards | GOV-03, LKPP-01/02/03/06, PROH-02 | **SHOULD HAVE** |
| **9.7 Regulatory Change Management** | Versioned tax_rules/regulatory_reference, §20 process, watch items — **cross-cutting governance capability** (bukan dependency runtime yang memblok submodule lain) | §20, DOC-05 | **SHOULD HAVE** (cross-cutting governance) |
| **9.8 Security Governance** | Incident response plan, processor agreements, least privilege; sisa SEC menyatu lintas M1–8 | SEC-01..07, PSE-SANC-001, GOV-05 | MUST (mostly EXTEND) |

**Dependency order (desain):** (0) shared kernel: RuleSnapshot + RegulatoryReference + HumanReviewCase + capabilities/legal functions → (1) 9.1 → (2) 9.5 → (3) 9.2 → (4) 9.4 → (5) 9.3 → (6) 9.6 → (7) 9.8. **9.7 Regulatory Change Management = cross-cutting governance capability**: menyuplai `RegulatoryReference`/effective-dated rule versioning ke shared kernel dan dipakai lintas submodule (9.1, 9.5, dsb.); **bukan** tahap runtime yang memblok submodule lain.

**Scope boundaries:** FUTURE/OUT-OF-SCOPE dari semua submodule: Coretax API direct (TAX-CRT-01 — OPTIONAL/business decision), Certified TTE signing module (B2B-04 — FUTURE/BLOCKED), cross-border jurisdiction (§23 #8). Modul 1–8 **tidak di-redesign**; hanya KEEP/EXTEND/MODIFY/DEPRECATE sesuai bukti kebutuhan (§24, keputusan desain Module 1–8).

---

## 10. Design Preconditions (fact-specific / legal-review — §24.1)

Prekondisi berikut harus **diselesaikan sebelum mengklaim applicability**; sistem menyimpan state-nya sebagai data (`UNRESOLVED` / `PENDING_LEGAL_REVIEW` / `APPLICABILITY_UNKNOWN`), **tidak** sebagai kesimpulan deterministik:

| # | Precondition | Rule IDs | Class |
|---|---|---|---|
| 1 | Status PKP (sell-side e-Faktur issuance) | TAX-PPN-01 | Tax / legal review — `UNVERIFIED` |
| 2 | PPh applicability (23/21 exact model) | TAX-PPH-01/02/03 | Tax / legal review |
| 3 | B2G VAT collector status customer base aktual | TAX-PPN-04 | Tax / legal review |
| 4 | DPO / PDP function officer applicability | PDP-DPO-001, ROLE-02 | Legal review (conditional) |
| 5 | TKDN/SNI applicability ke lini produk AJM | TKDN-01/02 | Procurement / legal review |
| 6 | PMSE classification (Pedagang vs Retail Online vs platform) | PMSE-01..04 | Legal review (conditional) |

---

## 11. Professional-Review Blockers (§23)

Item berikut **tetap unresolved** dan **tidak boleh menjadi kesimpulan legal deterministik** — ditandai `REQUIRE_REVIEW` / `REQUIRES_PROFESSIONAL_REVIEW`:

- PPh 23/21 treatment exact model (TAX-PPH-01/02; §23 #4/#19).
- Basis hukum TAX-PPH-03 (counterparty tax certificate/NPWP evidence) — `REQUIRES PROFESSIONAL TAX REVIEW` (§23 #13).
- B2G VAT collection scope (PMK 59/2022) untuk customer base aktual (§23 #10).
- Klasifikasi PMSE (Pedagang vs Retail Online) (§23 #1/#17).
- Klasifikasi strategic data (PSE-DATA-003) (§23 — fact-specific).
- Trigger DPO (PDP-DPO-001) — conditional pada skala/jenis pemrosesan (§23 #2).
- Retention untuk RFQ/order/PO dan BAST — `UNDEFINED — REQUIRES DOCUMENT-SPECIFIC LEGAL RULE` (§19).
- Klasifikasi AJM dibawah Permendag 31/2023 (§23 #1).
- Status dunia nyata lisensi/sertifikat/registrasi AJM — `UNVERIFIED`, butuh bukti AJM (§23 #11).

---

## 12. Panduan UI/UX (Frontend Guidelines)

*   **Framework:** Tampilan antarmuka, khususnya tata letak menu dan navigasi, dibangun menggunakan *utility classes* dari Tailwind CSS untuk memastikan kerapian dan konsistensi visual.
*   **Ikonografi:** Penggunaan elemen visual yang profesional dan tematik. Misalnya, ikon `laptop-code` untuk menu IT/Sistem, `chart-line` untuk dasbor analitik penjualan, dan `box-open` untuk manajemen inventaris/katalog barang.
*   **Aksesibilitas:** Desain harus bersih (*clean*), berfokus pada kemudahan pembacaan data, responsif di berbagai perangkat, dan menghindari elemen dekoratif yang mengganggu proses administratif.
*   **Label status:** Label status order internal (mis. "Menunggu Konfirmasi", "Diproses", "Dikirim", "Diterima", "Selesai") **tidak pernah** ditampilkan sebagai status pengadaan resmi; status eksternal hanya dari metadata eksternal dengan indikator sumber (GOV-04).

## 13. Matriks Traceability Rule ID (98 Rule → Spesifikasi)

Semua **98 Rule ID** dari `REGULATORY_RULEBOOK.md` terlacak di set spesifikasi revisi (PRD + Architecture + Database Schema). Kolom `PRD` = bagian PRD ini; `Arch` = bagian `docs/architecture.md`; `DB` = entitas `docs/database_schema.md`.

### 13.1 Prohibition & Governance (PROH, GOV)
| Rule ID | PRD | Arch | DB |
|---|---|---|---|
| PROH-01 | §2.2, §6, §10 | §3.8, §5 | document_evidence, human_review_cases |
| PROH-02 | §2.2, §8 (Katalog) | §3.4 | products.e_catalog_status, product_certifications |
| PROH-03 | §2.2, §8 (Katalog) | §3.4 | product_certifications |
| PROH-04 | §2.2, §10 | §3.12 | orders.external_* |
| PROH-05 | §6, §9.3 | §3.6, §5.D | statutory_timers, consent_records |
| PROH-06 | §6, §9.3 | §3.6 | incident_register, breach_notifications |
| PROH-07 | §9.3 | §3.6 | statutory_timers, breach_notifications |
| PROH-08 | §10 | §3.7 | retention_policies |
| GOV-01 | §3, §6, §10 | §3.12 | document_evidence.label, orders.external_* |
| GOV-02 | §3, §9.2 | §3.4 | supplier_eligibility |
| GOV-03 | §3, §6 | §2, §3.11, §3.12 | orders.channel, procurement_method |
| GOV-04 | §3, §12 | §3.12 | orders.external_* |
| GOV-05 | §2.2, §6, §10 | §3.8, §5 | document_evidence.label, human_review_cases |

### 13.2 Procurement Channel (LKPP, TKDN, B2B, ETS)
| Rule ID | PRD | Arch | DB |
|---|---|---|---|
| LKPP-01 | §3 | §3.11 | orders.channel, procurement_method |
| LKPP-02 | §3 | §3.11 | orders.external_* |
| LKPP-03 | §3, §8 (Order) | §3.12 | orders.procurement_method, orders.external_* |
| LKPP-04 | §3 (kategori perdagangan) | §3.11 | orders.channel (kategori) |
| LKPP-05 | §8 (Katalog) | §3.4 | products.e_catalog_status, e_catalog_verified_at |
| LKPP-06 | §8 (Katalog) | §3.4 | products.e_catalog_status, product_certifications |
| TKDN-01 | §8 (Katalog), §9.2 | §3.4 | product_certifications (cert_type='TKDN') |
| TKDN-02 | §8 (Katalog), §9.2 | §3.4 | product_certifications.issuer/issued_at |
| TKDN-03 | §9.2 | §3.4 | product_certifications.expires_at |
| TKDN-04 | §8 (Katalog) | §3.4 | products.tkdn_percentage (DERIVED) |
| TKDN-05 | §9.2 | §3.4 | product_certifications.tkdn_percentage |
| B2B-01 | §6, §11 | §3.5 | consent_records |
| B2B-02 | §6, §10 | §3.8, §5 | document_evidence |
| B2B-03 | §6, §11 | §3.5 | consent_records.purpose (prinsip minimal) |
| B2B-04 | §6, §11 | §3.5 | consent_records, statutory_timers |
| B2B-05 | §6, §11 | §3.5 | users.tos_accepted_at, consent_records |
| ETS-01 | §6, §11 | §3.5 | consent_records |
| ETS-02 | §10 | §3.7 | retention_policies (retensi persetujuan) |
| ETS-03 | §6, §11 | §3.5 | consent_records, statutory_timers |

### 13.3 Tax (TAX-PPN, TAX-PPH, TAX-CRT)
| Rule ID | PRD | Arch | DB |
|---|---|---|---|
| TAX-PPN-01 | §7 | §3.2 | tax_rules, rule_snapshots (per line item), invoice_rule_snapshots, faktur_codes |
| TAX-PPN-02 | §7 | §3.2 | faktur_codes |
| TAX-PPN-03 | §7, §10 | §3.2 | tax_rules (resolved tax context), faktur_codes (code resolution), invoices.faktur_pajak_number, rule_snapshots/invoice_rule_snapshots (E-Faktur/reporting data assembly) |
| TAX-PPN-04 | §7, §10 | §3.2 | order_items.collector_status_snapshot, tax_rules.vat_collector_status |
| TAX-PPN-05 | §7, §10 | §3.2 | supplier_eligibility.pkp_status, users.npwp_number |
| TAX-PPN-06 | §7 | §3.2 | N/A — NOT APPLICABLE (tidak ada fitur "SPT Tahunan PPN"; regression/negative requirement) |
| TAX-PPH-01 | §7, §11 | §3.2 | tax_rules.withholding_rule (REVIEW) |
| TAX-PPH-02 | §7, §11 | §3.2 | tax_rules.withholding_rule (REVIEW) |
| TAX-PPH-03 | §7 | §3.2 | rule_snapshots.withholding_snapshot |
| TAX-CRT-01 | §7, §11 | §3.2 | tax_rules.source_version (Coretax) |

**Klarifikasi traceability (§13.3):**
- TAX-PPN-03 = **E-Faktur Issuance & Reporting** — **bukan** "faktur code 03". Trace: `tax_rules` (resolved tax context) → `faktur_codes` (code resolution) → kolom invoice/faktur → perakitan data E-Faktur/reporting dari `rule_snapshots`/`invoice_rule_snapshots`.
- TAX-PPN-06 = **tidak ada konsep SPT Tahunan PPN** → DB = N/A; hanya persyaratan regresi/negatif: fitur "SPT Tahunan PPN" tidak boleh ada.
- **Sumber kebenaran faktur code (TAX-PPN-02/03):** `faktur_codes` = reference/rule catalog (kanonik); `tax_rules.faktur_code` = TaxRule ter-resolve yang mereferensikan code dari katalog; `rule_snapshots.faktur_code` = snapshot transaksi immutabel. **Tidak ada dua representasi otoritatif dari rule kode yang sama.**

### 13.4 PDP / Privacy (PDP-RIGHT, PDP-PROC, PDP-BREACH, PDP-3X24, PDP-DPO)
| Rule ID | PRD | Arch | DB |
|---|---|---|---|
| PDP-RIGHT-001 | §9.3 | §3.6 | data_subject_requests.right_code |
| PDP-RIGHT-002 | §9.3 | §3.6 | data_subject_requests.right_code |
| PDP-RIGHT-003 | §9.3 | §3.6 | data_subject_requests.right_code |
| PDP-RIGHT-004 | §9.3 | §3.6 | data_subject_requests.right_code |
| PDP-RIGHT-005 | §9.3 | §3.6 | data_subject_requests.right_code, consent_records |
| PDP-RIGHT-006 | §9.3 | §3.6 | data_subject_requests.right_code |
| PDP-RIGHT-007 | §9.3 | §3.6 | data_subject_requests.right_code (dibatasi 3x24h) |
| PDP-RIGHT-008 | §9.3 | §3.6 | data_subject_requests.right_code |
| PDP-RIGHT-009 | §9.3 | §3.6 | data_subject_requests.right_code |
| PDP-PROC-001 | §9.3 | §3.6 | data_subject_requests (channel, deadline, status) |
| PDP-BREACH-001 | §9.3 | §3.6 | breach_notifications, incident_register |
| PDP-3X24-001 | §9.3 | §3.6, §5.D | statutory_timers.deadline_at (SOURCE OF TRUTH; enforcement=STOP_PROCESSING), consent_records.withdrawal_deadline_at (DERIVED/cached projection) |
| PDP-3X24-002 | §9.3 | §3.6, §5.D | statutory_timers (enforcement=SUSPEND_RESTRICT_PROCESSING) |
| PDP-3X24-003 | §9.3 | §3.6, §5.D | statutory_timers (enforcement=ESCALATION_VIOLATION_AUDIT), breach_notifications |
| PDP-DPO-001 | §4, §9.3, §11 | §3.3 | legal_function_assignments (canonical; function_category=CONDITIONAL_LEGAL), dp_roles (DPO-only specialized record) |
| PDP-DPO-002 | §4, §9.3, §11 | §3.3 | legal_function_assignments.appointment_basis (canonical), dp_roles.appointment_basis (projection) |

### 13.5 PSE (PSE-REG, PSE-GOV, PSE-DATA, PSE-AUDIT, PSE-SEC, PSE-CERT, PSE-SANC)
| Rule ID | PRD | Arch | DB |
|---|---|---|---|
| PSE-REG-001 | §9.3 | §3.5 | pse_registration (registration_status, applicability) |
| PSE-REG-002 | §9.3 | §3.5 | pse_registration (maintenance; registration_status) |
| PSE-REG-003 | §9.3 | §3.5 | pse_registration.pse_registration_number |
| PSE-GOV-001 | §9.3 | §3.5 | pse_registration (governance attrs) |
| PSE-GOV-002 | §9.3 | §3.5 | pse_registration (governance attrs) |
| PSE-GOV-003 | §9.3 | §3.5 | pse_registration (governance attrs) |
| PSE-DATA-001 | §9.3 | §3.5 | pse_registration (data classification) |
| PSE-DATA-002 | §9.3 | §3.5 | pse_registration (data classification) |
| PSE-DATA-003 | §9.3 | §3.5 | pse_registration (strategic data: REVIEW) |
| PSE-AUDIT-001 | §6, §10 | §3.5, §5.C | audit_trail_mapping |
| PSE-SEC-001 | §9.3 | §3.5, §3.10 | pse_registration, audit_logs |
| PSE-CERT-001 | §9.3 | §3.5 | pse_certificates |
| PSE-SANC-001 | §9.3 | §3.5 | pse_registration (sanksi) |

### 13.6 PMSE (PMSE-01..04)
| Rule ID | PRD | Arch | DB |
|---|---|---|---|
| PMSE-01 | §9.2, §11 | §3.6 | supplier_eligibility (klasifikasi PMSE) |
| PMSE-02 | §9.2, §11 | §3.6 | supplier_eligibility (klasifikasi PMSE) |
| PMSE-03 | §9.2, §11 | §3.6 | supplier_eligibility (klasifikasi PMSE) |
| PMSE-04 | §9.2, §11 | §3.6 | supplier_eligibility (klasifikasi PMSE) |

### 13.7 Security (SEC-01..07)
| Rule ID | PRD | Arch | DB |
|---|---|---|---|
| SEC-01 | §9.3 | §3.10 | audit_logs |
| SEC-02 | §9.3 | §3.10 | incident_register |
| SEC-03 | §9.3 | §3.10 | audit_logs |
| SEC-04 | §9.3 | §3.10 | audit_logs (auth) |
| SEC-05 | §9.3 | §3.10 | audit_logs (auth) |
| SEC-06 | §9.3 | §3.10 | processor_agreements |
| SEC-07 | §6, §11 | §3.10 | consent_records, users.tos_accepted_at |

### 13.8 Documents & Retention (DOC-01..05)
| Rule ID | PRD | Arch | DB |
|---|---|---|---|
| DOC-01 | §10 | §3.7, §5.A | document_evidence |
| DOC-02 | §10 | §3.7 | document_evidence.sha256_hash |
| DOC-03 | §10 | §3.7 | document_evidence.label |
| DOC-04 | §10 | §3.7 | retention_policies |
| DOC-05 | §10 | §3.7 | document_evidence.template_version |

### 13.9 Roles & Legal Functions (ROLE-01..08)
| Rule ID | PRD | Arch | DB |
|---|---|---|---|
| ROLE-01 | §4 | §3.3 | capabilities, role_capabilities |
| ROLE-02 | §4 | §3.3 | legal_function_assignments (canonical; function_category=CONDITIONAL_LEGAL), dp_roles (DPO-only specialized projection; bukan source of truth kedua) |
| ROLE-03 | §4 | §3.3 | legal_function_assignments (REGULATORY_LEGAL bila diwajibkan peraturan) |
| ROLE-04 | §4, §9.5 | §3.3, §3.8 | legal_function_assignments (GOVERNANCE_OPERATIONAL), human_review_cases |
| ROLE-05 | §4, §9.5 | §3.3, §3.8 | legal_function_assignments (GOVERNANCE_OPERATIONAL), human_review_cases |
| ROLE-06 | §4 | §3.3 | legal_function_assignments (GOVERNANCE_OPERATIONAL) |
| ROLE-07 | §4 | §3.3 | legal_function_assignments (GOVERNANCE_OPERATIONAL; bukan legal function default) |
| ROLE-08 | §4 | §3.3 | legal_function_assignments (GOVERNANCE_OPERATIONAL; bukan legal function default) |

### 13.10 Incidents (INC-PSE, INC-PDP)
| Rule ID | PRD | Arch | DB |
|---|---|---|---|
| INC-PSE-001 | §9.3 | §3.6 | incident_register (incident_class='PSE_DISRUPTION') |
| INC-PSE-002 | §9.3 | §3.6 | incident_register (incident_class='PSE_PDP_FAILURE') |
| INC-PDP-001 | §9.3 | §3.6 | incident_register (incident_class='PDP_BREACH') |

**Verifikasi:** seluruh 98 Rule ID tercantum di atas; tidak ada rule yang dihapus diam-diam. Rule yang berstatus *blocker/uncertain* tetap non-deterministik (lih. §10–§11).
