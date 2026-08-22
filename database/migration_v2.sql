-- =====================================================
-- MiniERP v2.0 - Migration: Fitur Lanjutan
-- =====================================================

SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------
-- Harga Bertingkat per Customer (price level)
-- -----------------------------------------------------
DROP TABLE IF EXISTS price_levels;
CREATE TABLE price_levels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    customer_id INT NOT NULL,
    price DECIMAL(15,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_price_level (product_id, customer_id),
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- Sales Invoice (Faktur Penjualan)
-- -----------------------------------------------------
DROP TABLE IF EXISTS sales_invoices;
CREATE TABLE sales_invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(30) NOT NULL UNIQUE,
    so_id INT NOT NULL,
    customer_id INT NOT NULL,
    invoice_date DATE NOT NULL,
    due_date DATE NOT NULL,
    total DECIMAL(15,2) NOT NULL,
    paid DECIMAL(15,2) NOT NULL DEFAULT 0,
    status ENUM('UNPAID','PARTIAL','PAID','OVERDUE') NOT NULL DEFAULT 'UNPAID',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (so_id) REFERENCES sales_orders(id),
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- Purchase Invoice (Faktur Pembelian / Hutang)
-- -----------------------------------------------------
DROP TABLE IF EXISTS purchase_invoices;
CREATE TABLE purchase_invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(30) NOT NULL UNIQUE,
    po_id INT NOT NULL,
    supplier_id INT NOT NULL,
    invoice_date DATE NOT NULL,
    due_date DATE NOT NULL,
    total DECIMAL(15,2) NOT NULL,
    paid DECIMAL(15,2) NOT NULL DEFAULT 0,
    status ENUM('UNPAID','PARTIAL','PAID','OVERDUE') NOT NULL DEFAULT 'UNPAID',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (po_id) REFERENCES purchase_orders(id),
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- Pembayaran (Payment) - untuk AR & AP
-- -----------------------------------------------------
DROP TABLE IF EXISTS payments;
CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payment_number VARCHAR(30) NOT NULL UNIQUE,
    payment_type ENUM('RECEIVE','PAY') NOT NULL, -- RECEIVE: terima dari customer, PAY: bayar ke supplier
    invoice_type ENUM('SALES','PURCHASE') NOT NULL,
    invoice_id INT NOT NULL,
    payment_date DATE NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    payment_method VARCHAR(50) NOT NULL DEFAULT 'Transfer Bank',
    notes VARCHAR(255),
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- Delivery Order (Surat Jalan) - pengiriman parsial
-- -----------------------------------------------------
DROP TABLE IF EXISTS delivery_orders;
CREATE TABLE delivery_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    do_number VARCHAR(30) NOT NULL UNIQUE,
    so_id INT NOT NULL,
    delivery_date DATE NOT NULL,
    driver VARCHAR(100),
    vehicle VARCHAR(100),
    status ENUM('SHIPPED','DELIVERED','CANCELLED') NOT NULL DEFAULT 'SHIPPED',
    notes VARCHAR(255),
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (so_id) REFERENCES sales_orders(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS delivery_order_items;
CREATE TABLE delivery_order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    do_id INT NOT NULL,
    product_id INT NOT NULL,
    qty DECIMAL(15,2) NOT NULL,
    FOREIGN KEY (do_id) REFERENCES delivery_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- Sales Return (Retur Penjualan)
-- -----------------------------------------------------
DROP TABLE IF EXISTS sales_returns;
CREATE TABLE sales_returns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    return_number VARCHAR(30) NOT NULL UNIQUE,
    so_id INT NOT NULL,
    return_date DATE NOT NULL,
    total DECIMAL(15,2) NOT NULL DEFAULT 0,
    reason VARCHAR(255),
    status ENUM('DRAFT','CONFIRMED','CANCELLED') NOT NULL DEFAULT 'DRAFT',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (so_id) REFERENCES sales_orders(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS sales_return_items;
CREATE TABLE sales_return_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    return_id INT NOT NULL,
    product_id INT NOT NULL,
    qty DECIMAL(15,2) NOT NULL,
    price DECIMAL(15,2) NOT NULL,
    subtotal DECIMAL(15,2) NOT NULL,
    FOREIGN KEY (return_id) REFERENCES sales_returns(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- Purchase Return (Retur Pembelian)
-- -----------------------------------------------------
DROP TABLE IF EXISTS purchase_returns;
CREATE TABLE purchase_returns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    return_number VARCHAR(30) NOT NULL UNIQUE,
    po_id INT NOT NULL,
    return_date DATE NOT NULL,
    total DECIMAL(15,2) NOT NULL DEFAULT 0,
    reason VARCHAR(255),
    status ENUM('DRAFT','CONFIRMED','CANCELLED') NOT NULL DEFAULT 'DRAFT',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (po_id) REFERENCES purchase_orders(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS purchase_return_items;
CREATE TABLE purchase_return_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    return_id INT NOT NULL,
    product_id INT NOT NULL,
    qty DECIMAL(15,2) NOT NULL,
    price DECIMAL(15,2) NOT NULL,
    subtotal DECIMAL(15,2) NOT NULL,
    FOREIGN KEY (return_id) REFERENCES purchase_returns(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- Stock Opname (Physical Count)
-- -----------------------------------------------------
DROP TABLE IF EXISTS stock_opnames;
CREATE TABLE stock_opnames (
    id INT AUTO_INCREMENT PRIMARY KEY,
    opname_number VARCHAR(30) NOT NULL UNIQUE,
    opname_date DATE NOT NULL,
    status ENUM('OPEN','CONFIRMED','CANCELLED') NOT NULL DEFAULT 'OPEN',
    notes VARCHAR(255),
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS stock_opname_items;
CREATE TABLE stock_opname_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    opname_id INT NOT NULL,
    product_id INT NOT NULL,
    system_qty DECIMAL(15,2) NOT NULL,
    physical_qty DECIMAL(15,2) DEFAULT NULL,
    difference DECIMAL(15,2) GENERATED ALWAYS AS (physical_qty - system_qty) VIRTUAL,
    FOREIGN KEY (opname_id) REFERENCES stock_opnames(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- Activity Log / Audit Trail
-- -----------------------------------------------------
DROP TABLE IF EXISTS activity_logs;
CREATE TABLE activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    module VARCHAR(50) NOT NULL,
    description VARCHAR(500),
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;
