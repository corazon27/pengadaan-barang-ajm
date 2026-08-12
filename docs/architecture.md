# System Architecture & Technical Specification
**Project:** Standalone B2B & B2G E-Procurement Platform
**Framework:** Laravel 13 (PHP 8.3+)
**Database:** MySQL
**Document Status:** Complete Architecture Draft

---

## 1. Core Architecture Philosophy & Compliance

Platform ini dirancang khusus sebagai sistem pengadaan mandiri (*single-supplier platform*) dengan model operasional ganda:
1.  **Siklus Transaksi B2B (Swasta):** Diproses secara langsung *end-to-end* di dalam platform, mulai dari penawaran harga (RFQ), penerbitan PO, pengiriman barang, BAST, hingga sistem penagihan berbasis *Term of Payment* (TOP).
2.  **Siklus Transaksi B2G (Pemerintah):** Platform berfungsi sebagai portal negosiasi teknis, katalog digital, dan pencetakan dokumen pra-pengadaan. Transaksi eksekusi anggaran APBN/APBD dialihkan (*handed-off*) ke etalase resmi perusahaan di **e-Katalog LKPP** untuk memenuhi Perpres Pengadaan Barang/Jasa Pemerintah.
3.  **Manajemen Dana Talangan:** Perhitungan piutang (*Receivables*) dikunci berdasarkan tanggal penandatanganan Berita Acara Serah Terima (BAST), bukan tanggal pemesanan awal.

---

## 2. Technology Stack & Dependencies

*   **Core Framework:** Laravel 13 (PHP 8.3+ with `declare(strict_types=1);`)
*   **Database Engine:** MySQL 8.4 (Laragon): native ENUM, UUID/CHAR(36), JSON untuk Audit Log
*   **Frontend & UI Layer:**
    *   Blade Templating Engine
    *   Tailwind CSS (Build Tool via Vite)
    *   Iconography: FontAwesome / Heroicons (`laptop-code`, `chart-line`, `box-open`, `file-signature`, `receipt`)
*   **Document Rendering Engine:** `barryvdh/laravel-dompdf` atau `spatie/laravel-pdf` (Render Blade to PDF untuk RFQ, BAST, dan Invoice)
*   **File Storage Engine:** Local Private Disk / S3 Storage (Storage terproteksi untuk unggahan PO, BAST Scan, dan e-Faktur)
*   **Task Scheduler:** Laravel Console Cron (Monitoring piutang jatuh tempo & status *overdue*)

---

## 3. Subsistem & Modul Spesifik

### A. Authentication & Access Control (RBAC)
Menggunakan Laravel Gates/Policies yang terintegrasi dengan Enum `user_role`:
*   `SUPERADMIN`: Pemilik platform / Penyedia tunggal. Memiliki wewenang penuh atas manajemen katalog, verifikasi RFQ, penerbitan BAST, pencetakan Invoice, dan pemantauan piutang.
*   `BUYER_B2B`: Staf *purchasing* perusahaan swasta. Berhak membuat RFQ, mengunggah PO, menyetujui BAST, dan mengunduh Invoice.
*   `BUYER_B2G`: Pejabat Pembuat Komitmen (PPK) / Pejabat Pengadaan instansi pemerintah. Berhak membuat RFQ, mengunduh penawaran resmi, serta mengakses tautan etalase e-Katalog LKPP terkait.

### B. Order State Engine & Flow Validation
State Machine pada transaksi dikendalikan oleh kelas `App\Services\OrderService.php` dengan transisi status yang baku dan tidak dapat dilompati:

---

## 4. High-Level Technology Stack

*   **Backend Framework:** Laravel (PHP 8.3+)
*   **Database:** MySQL
*   **Frontend Engine:** Blade Templates + Tailwind CSS (di-compile via Vite) / Inertia.js (opsional)
*   **Authentication & RBAC:** Laravel Breeze / Sanctum + Native Gates/Policies (atau Spatie Laravel-Permission)
*   **PDF Generator:** `barryvdh/laravel-dompdf` atau `spatie/laravel-pdf` (untuk men-generate RFQ, BAST, dan Invoice)
*   **Storage Driver:** Local Disk / S3 Compatible (untuk menyimpan dokumen PO, BAST bertanda tangan, dan e-Faktur)

---

*   `DRAFT`: Pesanan dibuat oleh buyer atau dikonversi dari RFQ.
*   `WAITING_PO`: Menunggu buyer mengunggah file Surat Pesanan / Purchase Order (PO).
*   `PROCESSING`: Admin menyorot PO dan memproses pengadaan/pemenuhan barang (fase penalangan dana).
*   `SHIPPED`: Barang dalam pengiriman ke lokasi instansi/klien.
*   `BAST_SIGNED`: Dokumen BAST fisik telah ditandatangani di lapangan dan diunggah ke sistem. **Tanggal BAST menjadi janggal (*anchor*) perhitungan jatuh tempo.**
*   `INVOICED`: Admin menerbitkan Invoice Penagihan + e-Faktur Pajak.
*   `PAID`: Tagihan dilunasi oleh instansi/klien.

### C. Document Engine (PDF & Files Management)
Modul untuk membuat dan menyimpan dokumen hukum:
*   **RFQ Generator:** Mengompilasi data `rfqs` dan `rfq_items` menjadi Surat Penawaran Harga resmi ber-Kop Surat perusahaan (format PDF).
*   **BAST Management:** Generasi draf BAST otomatis, serta penampung unggahan scan BAST yang telah distempel/ditandatangani basah.
*   **Invoice & Tax Engine:** Generasi dokumen Invoice berbasis `signed_date` + `top_days`, serta penampung unggahan e-Faktur Pajak (`faktur_pajak_number`).

### D. Financial & Receivables Engine (TOP Monitoring)
*   **Jatuh Tempo Dynamic Calculation:** $Due Date = Signed Date (BAST) + TOP Days$.
*   **Cron Job Scheduler (`routes/console.php`):** Dijalankan setiap hari pukul 00:00 untuk mengecek tabel `invoices`. Jika $Current Date > Due Date$ dan status masih `UNPAID`, status otomatis diperbarui menjadi `OVERDUE` dan sistem mengirimkan notifikasi pengingat penagihan.

### E. Audit Trail & Compliance Log Subsystem
Setiap aksi kritis (perubahan harga produk, perubahan status pesanan, penerbitan invoice, atau unggahan BAST) dicatat ke tabel `audit_logs` melalui Laravel Event Listeners (`App\Listeners\LogActivity.php`), menyimpan kondisi data sebelum (`previous_state`) dan sesudah (`new_state`) dalam format JSON.

---

## 5. Peta Struktur Folder Proyek (Laravel 13 Convention)

```text
GEMINI-PENGADAAN/
├── app/
│   ├── Enums/
│   │   ├── UserRole.php           # Enum: SUPERADMIN, BUYER_B2B, BUYER_B2G
│   │   ├── RfqStatus.php          # Enum: SUBMITTED, REVIEWED, APPROVED, etc.
│   │   ├── OrderStatus.php        # Enum: DRAFT, WAITING_PO, BAST_SIGNED, etc.
│   │   └── InvoiceStatus.php      # Enum: UNPAID, OVERDUE, PAID
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Admin/             # Product, Order, BAST, Invoice Controllers
│   │   │   ├── Buyer/             # RFQ, PO Upload Controllers
│   │   │   └── Auth/              # Login/Register Controllers
│   │   ├── Middleware/
│   │   │   └── EnsureUserRole.php # Middleware RBAC Check
│   │   └── Requests/              # Form Request Validations (StoreProduct, StoreRfq)
│   ├── Models/
│   │   ├── User.php
│   │   ├── Product.php            # With TKDN & Price Breakdown logic
│   │   ├── Rfq.php
│   │   ├── Order.php
│   │   ├── BastDocument.php
│   │   ├── Invoice.php
│   │   └── AuditLog.php
│   ├── Policies/                  # OrderPolicy, RfqPolicy, InvoicePolicy
│   ├── Services/
│   │   ├── OrderService.php       # State machine & transition validator
│   │   ├── InvoiceService.php     # TOP & Due Date calculator
│   │   └── PdfService.php         # DomPDF Wrapper
│   └── View/
│       └── Components/            # Blade UI Components (StatusBadge, DataCard)
├── bootstrap/
│   ├── app.php                    # Registrasi Middleware & Console Commands (Laravel 13)
│   └── providers.php
├── database/
│   ├── migrations/                # Pemetaan 1:1 dari database_schema.md
│   └── seeders/                   # Initial Superadmin & Sample Products
├── docs/                          # DOKUMENTASI SISTEM & KONTROL AI
│   ├── PRD.md                     # Product Requirements Document
│   ├── database_schema.md         # Database DDL & Specification
│   └── architecture.md            # System Architecture (File Ini)
├── resources/
│   ├── css/
│   │   └── app.css                # Tailwind CSS Setup
│   ├── js/
│   │   └── app.js                 # Vite Entrypoint
│   └── views/
│       ├── components/            # UI kit (Tables, Modals, Badges)
│       ├── layouts/               # App, Admin, & Guest Layouts
│       ├── admin/                 # Dashboard Admin & Procurement Management
│       ├── buyer/                 # Catalog, RFQ, & Order Tracker Views
│       ├── pdf/                   # Printable Blade Templates (RFQ, BAST, Invoice)
│       └── cara-order.blade.php   # Page Panduan Prosedur Pembelian
├── routes/
│   ├── web.php                    # Web Portal Routes
│   ├── api.php                    # Internal API Endpoints
│   └── console.php                # Scheduled Tasks (Overdue Invoice Checker)
└── storage/
    └── app/
        └── private/               # Terproteksi: uploads/po/, uploads/bast/, uploads/faktur/
