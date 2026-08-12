# Product Requirements Document (PRD)
**Project:** Standalone B2B & B2G E-Procurement Platform
**Status:** Draft / Work in Progress
**Date Updated:** 11 Agustus 2026

## 1. Visi Produk
Membangun platform katalog produk dan e-commerce mandiri (sebagai *single-supplier*) yang dikhususkan untuk pengadaan barang bagi instansi pemerintah (B2G) dan perusahaan swasta (B2B). Sistem ini dirancang untuk mengakomodasi siklus pengadaan yang kompleks, termasuk sistem *Term of Payment* (TOP) atau dana talangan, dengan mematuhi regulasi hukum pengadaan di Indonesia.

## 2. Kepatuhan Hukum & Regulasi (Compliance)
*   **Pendaftaran PSE:** Sistem harus memenuhi standar untuk didaftarkan sebagai Penyelenggara Sistem Elektronik (PSE) Kominfo Lingkup Privat.
*   **Integrasi e-Katalog LKPP (Khusus B2G):** Sistem tidak memproses pembayaran langsung dari APBN/APBD. Untuk klien pemerintah, platform berfungsi sebagai alat negosiasi, penerbitan penawaran, dan spesifikasi teknis. Transaksi final harus diarahkan ke etalase resmi perusahaan di e-Katalog LKPP.
*   **Legalitas Perusahaan:** Sistem mensyaratkan operasional di bawah badan hukum yang memiliki NIB, berstatus PKP (Pengusaha Kena Pajak) untuk penerbitan e-Faktur, dan KSWP valid.

## 3. User Personas & Role-Based Access Control (RBAC)
1.  **Superadmin / Supplier (Pemilik Sistem):** Mengelola katalog produk, menyetujui RFQ, memproses pesanan, mengunggah BAST, dan menerbitkan Invoice.
2.  **Buyer - B2B (Swasta):** Staf *purchasing* swasta yang dapat melakukan transaksi penuh dari ujung ke ujung di dalam platform.
3.  **Buyer - B2G (Pemerintah):** Staf/PPK yang mencari referensi spesifikasi, meminta penawaran resmi, dan mencetak dokumen pra-pengadaan.

## 4. Alur Kerja Utama (Core Workflows)
Sistem menggunakan alur pemesanan berbasis dokumen dan kredit (TOP), bukan *instant checkout*:
1.  **Request for Quotation (RFQ):** Klien memilih produk dan *submit* permintaan. Sistem menghasilkan dokumen Surat Penawaran Harga (PDF) secara otomatis.
2.  **Purchase Order (PO):** Klien mengunggah dokumen PO resmi mereka ke dalam sistem sebagai tanda persetujuan.
3.  **Order Fulfillment:** Superadmin memproses dan mengirimkan pesanan (masa talangan dana).
4.  **Berita Acara Serah Terima (BAST):** Fitur untuk *generate* dan *upload* dokumen BAST yang telah ditandatangani kedua belah pihak di lapangan.
5.  **Invoicing & TOP:** Setelah status pesanan menjadi `BAST_SIGNED`, Superadmin dapat menerbitkan Invoice dan e-Faktur. Argo *Term of Payment* (misal: 30, 60, 90 hari) otomatis berjalan.

## 5. Kebutuhan Fitur (Feature Requirements)
### Katalog & Etalase Produk
*   **Indikator TKDN:** Sistem wajib menampilkan persentase Tingkat Komponen Dalam Negeri (TKDN) pada setiap produk.
*   **Transparansi Harga:** Struktur harga harus jelas (Harga Dasar, Margin, Pajak/PPN, Estimasi Ongkir).
*   **Spesifikasi Teknis:** Dukungan untuk *upload datasheet* (PDF), status SNI, dan informasi garansi.

### Manajemen Order & Piutang
*   **Dashboard Status:** Pelacakan visual pesanan (`Draft`, `Waiting PO`, `Processing`, `Shipped`, `BAST Signed`, `Invoiced`, `Paid`).
*   **Sistem Pengingat:** Notifikasi jatuh tempo pembayaran (*Due Date Alert*) berdasarkan perhitungan TOP.

### Antarmuka Publik & Edukasi
*   **Halaman Cara Order:** Sistem menyediakan halaman "Cara Order" atau panduan khusus untuk mengedukasi klien baru terkait prosedur pemesanan yang berbeda dari e-commerce ritel biasa.

## 6. Panduan UI/UX (Frontend Guidelines)
*   **Framework:** Tampilan antarmuka, khususnya tata letak menu dan navigasi, dibangun menggunakan *utility classes* dari Tailwind CSS untuk memastikan kerapian dan konsistensi visual.
*   **Ikonografi:** Penggunaan elemen visual yang profesional dan tematik. Misalnya, mengimplementasikan ikon `laptop-code` untuk menu IT/Sistem, `chart-line` untuk dasbor analitik penjualan, dan `box-open` untuk manajemen inventaris/katalog barang.
*   **Aksesibilitas:** Desain harus bersih (*clean*), berfokus pada kemudahan pembacaan data, responsif di berbagai perangkat, dan menghindari elemen dekoratif yang mengganggu proses administratif.
