# Database Schema Specification

```sql
-- =============================================================================
-- DATABASE SCHEMA DDL: Standalone B2B & B2G E-Procurement Platform
-- Database Target: MySQL 8.4 (Laragon)
-- =============================================================================

-- =============================================================================
-- 1. TABLES DEFINITION
-- =============================================================================

-- -----------------------------------------------------------------------------
-- A. USERS & ORGANIZATIONS
-- -----------------------------------------------------------------------------
CREATE TABLE users (
  id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  email VARCHAR(255) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(100) NOT NULL,
  role ENUM('SUPERADMIN','BUYER_B2B','BUYER_B2G') NOT NULL DEFAULT 'BUYER_B2B',
  company_name VARCHAR(200) NOT NULL,
  npwp_number VARCHAR(30),
  address TEXT NOT NULL,
  phone_number VARCHAR(20) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- -----------------------------------------------------------------------------
-- B. PRODUCTS
-- -----------------------------------------------------------------------------
CREATE TABLE products (
  id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  sku VARCHAR(50) UNIQUE NOT NULL,
  title VARCHAR(255) NOT NULL,
  slug VARCHAR(255) UNIQUE NOT NULL,
  description TEXT NOT NULL,
  base_price NUMERIC(15, 2) NOT NULL CHECK (base_price >= 0),
  margin_percentage NUMERIC(5, 2) DEFAULT 0.00 CHECK (margin_percentage >= 0),
  tax_rate_percentage NUMERIC(5, 2) DEFAULT 11.00 CHECK (tax_rate_percentage >= 0),
  estimated_shipping NUMERIC(15, 2) DEFAULT 0.00 CHECK (estimated_shipping >= 0),
  tkdn_percentage NUMERIC(5, 2) CHECK (tkdn_percentage >= 0 AND tkdn_percentage <= 100),
  is_sni BOOLEAN DEFAULT FALSE,
  warranty_info VARCHAR(100),
  datasheet_url VARCHAR(500),
  stock INTEGER DEFAULT 0 CHECK (stock >= 0),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- -----------------------------------------------------------------------------
-- C. REQUEST FOR QUOTATIONS (RFQs)
-- -----------------------------------------------------------------------------
CREATE TABLE rfqs (
  id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  rfq_number VARCHAR(50) UNIQUE NOT NULL,
  user_id CHAR(36) NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
  status ENUM('SUBMITTED','REVIEWED','APPROVED','REJECTED','CONVERTED_TO_ORDER') DEFAULT 'SUBMITTED',
  quotation_pdf_url VARCHAR(500),
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

-- -----------------------------------------------------------------------------
-- D. ORDERS & ORDER ITEMS
-- -----------------------------------------------------------------------------
CREATE TABLE orders (
  id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  order_number VARCHAR(50) UNIQUE NOT NULL,
  user_id CHAR(36) NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
  rfq_id CHAR(36) REFERENCES rfqs(id) ON DELETE SET NULL,
  status ENUM('DRAFT','WAITING_PO','PROCESSING','SHIPPED','BAST_SIGNED','INVOICED','PAID','CANCELLED') DEFAULT 'DRAFT',
  top_days INTEGER DEFAULT 30 CHECK (top_days >= 0),
  total_amount NUMERIC(15, 2) NOT NULL CHECK (total_amount >= 0),
  po_document_url VARCHAR(500),
  lkpp_product_url VARCHAR(500),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE order_items (
  id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  order_id CHAR(36) NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
  product_id CHAR(36) NOT NULL REFERENCES products(id) ON DELETE RESTRICT,
  quantity INTEGER NOT NULL CHECK (quantity > 0),
  unit_price NUMERIC(15, 2) NOT NULL CHECK (unit_price >= 0),
  subtotal NUMERIC(15, 2) NOT NULL CHECK (subtotal >= 0)
);

-- -----------------------------------------------------------------------------
-- E. BERITA ACARA SERAH TERIMA (BAST)
-- -----------------------------------------------------------------------------
CREATE TABLE bast_documents (
  id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  order_id CHAR(36) NOT NULL REFERENCES orders(id) ON DELETE RESTRICT,
  bast_number VARCHAR(50) UNIQUE NOT NULL,
  bast_document_url VARCHAR(500) NOT NULL,
  signed_date DATE NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- -----------------------------------------------------------------------------
-- F. INVOICES & TAXES
-- -----------------------------------------------------------------------------
CREATE TABLE invoices (
  id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  order_id CHAR(36) NOT NULL REFERENCES orders(id) ON DELETE RESTRICT,
  bast_id CHAR(36) NOT NULL REFERENCES bast_documents(id) ON DELETE RESTRICT,
  invoice_number VARCHAR(50) UNIQUE NOT NULL,
  faktur_pajak_number VARCHAR(50),
  invoice_pdf_url VARCHAR(500) NOT NULL,
  faktur_pajak_url VARCHAR(500),
  amount_due NUMERIC(15, 2) NOT NULL CHECK (amount_due >= 0),
  issued_date DATE NOT NULL,
  due_date DATE NOT NULL,
  status ENUM('UNPAID','OVERDUE','PAID') DEFAULT 'UNPAID',
  paid_at TIMESTAMP,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- -----------------------------------------------------------------------------
-- G. AUDIT LOGS
-- -----------------------------------------------------------------------------
CREATE TABLE audit_logs (
  id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  user_id CHAR(36) REFERENCES users(id) ON DELETE SET NULL,
  action VARCHAR(100) NOT NULL,
  entity_type VARCHAR(50) NOT NULL,
  entity_id CHAR(36) NOT NULL,
  previous_state JSON,
  new_state JSON,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =============================================================================
-- 2. INDEXES FOR PERFORMANCE OPTIMIZATION
-- =============================================================================

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

-- =============================================================================
-- 3. UPDATED_AT HANDLING
-- =============================================================================

-- Catatan: Kolom `updated_at` dikelola otomatis oleh Eloquent (Laravel)
-- melalui model events (`updating`). Tidak diperlukan trigger database.
```