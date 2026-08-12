# Project Changelog

## [Phase 1 - Inisiasi Fondasi] - 2026-08-12
- Configured `.env` for local MySQL (Laragon).
- Created database `pengadaan_barang_ajm`.
- Created PHP 8.3 Enums: `UserRole`, `RfqStatus`, `OrderStatus`, `InvoiceStatus`.
- Fixed PSR-4 autoload namespace structure for Enums under `app/Enums`.
- Created and executed MySQL database migrations for all tables:
  - `users` (procurement roles & company profiles)
  - `products` (TKDN, SNI, tax rates, margins)
  - `rfqs` & `rfq_items`
  - `orders` & `order_items`
  - `bast_documents`
  - `invoices`
  - `audit_logs`
- Created Eloquent Models with `HasUuids` trait, relationships, Enum casts, and strict type definitions.
