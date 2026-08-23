-- =====================================================
-- MiniERP v4.0 (Enterprise) - Expansion Migration
-- =====================================================

SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================
-- CRM
-- =====================================================

DROP TABLE IF EXISTS leads;
CREATE TABLE leads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lead_number VARCHAR(30) NOT NULL UNIQUE,
    company_name VARCHAR(150) NOT NULL,
    contact_name VARCHAR(100),
    email VARCHAR(100),
    phone VARCHAR(30),
    source VARCHAR(50),
    status ENUM('NEW','CONTACTED','QUALIFIED','CONVERTED','LOST') NOT NULL DEFAULT 'NEW',
    estimated_value DECIMAL(15,2) NOT NULL DEFAULT 0,
    assigned_to INT,
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (assigned_to) REFERENCES employees(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS opportunities;
CREATE TABLE opportunities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    opp_number VARCHAR(30) NOT NULL UNIQUE,
    lead_id INT,
    customer_id INT,
    name VARCHAR(200) NOT NULL,
    stage ENUM('PROSPECTING','QUALIFICATION','PROPOSAL','NEGOTIATION','CLOSED_WON','CLOSED_LOST') NOT NULL DEFAULT 'PROSPECTING',
    probability INT NOT NULL DEFAULT 10,
    expected_value DECIMAL(15,2) NOT NULL DEFAULT 0,
    expected_close_date DATE,
    assigned_to INT,
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE SET NULL,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to) REFERENCES employees(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS crm_activities;
CREATE TABLE crm_activities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    activity_type ENUM('CALL','MEETING','EMAIL','TASK','NOTE') NOT NULL DEFAULT 'NOTE',
    related_type ENUM('LEAD','OPPORTUNITY','CUSTOMER') NOT NULL,
    related_id INT NOT NULL,
    subject VARCHAR(200) NOT NULL,
    description TEXT,
    activity_date DATETIME,
    status ENUM('PENDING','DONE','CANCELLED') NOT NULL DEFAULT 'PENDING',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- =====================================================
-- TAX MANAGEMENT
-- =====================================================

DROP TABLE IF EXISTS taxes;
CREATE TABLE taxes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    type ENUM('PPN','PPH21','PPH22','PPH23','PPH4_2','BEA_MATERAI') NOT NULL,
    rate DECIMAL(5,2) NOT NULL, -- persen
    status TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

DROP TABLE IF EXISTS tax_transactions;
CREATE TABLE tax_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tax_number VARCHAR(30) NOT NULL UNIQUE,
    tax_id INT NOT NULL,
    reference_type VARCHAR(30) NOT NULL,
    reference_id INT NOT NULL,
    base_amount DECIMAL(15,2) NOT NULL,
    tax_amount DECIMAL(15,2) NOT NULL,
    status ENUM('DRAFT','POSTED','CANCELLED') NOT NULL DEFAULT 'DRAFT',
    posted_date DATE,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tax_id) REFERENCES taxes(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS e_faktur;
CREATE TABLE e_faktur (
    id INT AUTO_INCREMENT PRIMARY KEY,
    faktur_number VARCHAR(30) NOT NULL UNIQUE,
    customer_id INT NOT NULL,
    faktur_date DATE NOT NULL,
    dpp DECIMAL(15,2) NOT NULL,
    ppn DECIMAL(15,2) NOT NULL,
    total DECIMAL(15,2) NOT NULL,
    status ENUM('DRAFT','APPROVED','UPLOADED') NOT NULL DEFAULT 'DRAFT',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- =====================================================
-- MULTI-BRANCH
-- =====================================================

DROP TABLE IF EXISTS branches;
CREATE TABLE branches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    address TEXT,
    phone VARCHAR(30),
    warehouse_id INT,
    is_head_office TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================
-- SHIPMENT & LOGISTICS
-- =====================================================

DROP TABLE IF EXISTS carriers;
CREATE TABLE carriers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    type ENUM('TRUCK','SHIP','AIR','COURIER') NOT NULL DEFAULT 'TRUCK',
    contact VARCHAR(100),
    phone VARCHAR(30),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

DROP TABLE IF EXISTS shipments;
CREATE TABLE shipments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shipment_number VARCHAR(30) NOT NULL UNIQUE,
    do_id INT,
    carrier_id INT,
    tracking_number VARCHAR(100),
    ship_date DATE,
    estimated_arrival DATE,
    actual_arrival DATE NULL,
    status ENUM('PREPARING','SHIPPED','IN_TRANSIT','DELIVERED','CANCELLED') NOT NULL DEFAULT 'PREPARING',
    notes VARCHAR(255),
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (do_id) REFERENCES delivery_orders(id) ON DELETE SET NULL,
    FOREIGN KEY (carrier_id) REFERENCES carriers(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- =====================================================
-- LANDED COST
-- =====================================================

DROP TABLE IF EXISTS landed_costs;
CREATE TABLE landed_costs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lc_number VARCHAR(30) NOT NULL UNIQUE,
    po_id INT NOT NULL,
    cost_type ENUM('FREIGHT','INSURANCE','CUSTOMS','HANDLING','OTHER') NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    allocation_method ENUM('QTY','VALUE','MANUAL') NOT NULL DEFAULT 'VALUE',
    status ENUM('DRAFT','ALLOCATED','POSTED') NOT NULL DEFAULT 'DRAFT',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (po_id) REFERENCES purchase_orders(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS landed_cost_allocations;
CREATE TABLE landed_cost_allocations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lc_id INT NOT NULL,
    product_id INT NOT NULL,
    allocated_amount DECIMAL(15,2) NOT NULL,
    FOREIGN KEY (lc_id) REFERENCES landed_costs(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;

-- =====================================================
-- COMMISSION
-- =====================================================

DROP TABLE IF EXISTS commission_rules;
CREATE TABLE commission_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    type ENUM('PERCENTAGE','FIXED','TIERED') NOT NULL DEFAULT 'PERCENTAGE',
    base_value DECIMAL(5,2) NOT NULL,
    min_amount DECIMAL(15,2) DEFAULT 0,
    max_amount DECIMAL(15,2) DEFAULT NULL,
    status TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

DROP TABLE IF EXISTS commission_transactions;
CREATE TABLE commission_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    commission_number VARCHAR(30) NOT NULL UNIQUE,
    employee_id INT NOT NULL,
    period VARCHAR(7) NOT NULL,
    base_amount DECIMAL(15,2) NOT NULL,
    commission_amount DECIMAL(15,2) NOT NULL,
    status ENUM('DRAFT','APPROVED','PAID','CANCELLED') NOT NULL DEFAULT 'DRAFT',
    paid_date DATE,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- =====================================================
-- SERVICE & HELPDESK
-- =====================================================

DROP TABLE IF EXISTS tickets;
CREATE TABLE tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_number VARCHAR(30) NOT NULL UNIQUE,
    subject VARCHAR(200) NOT NULL,
    description TEXT,
    customer_id INT,
    category ENUM('TECHNICAL','BILLING','GENERAL','COMPLAINT','REQUEST') NOT NULL DEFAULT 'GENERAL',
    priority ENUM('LOW','MEDIUM','HIGH','URGENT') NOT NULL DEFAULT 'MEDIUM',
    status ENUM('OPEN','IN_PROGRESS','RESOLVED','CLOSED','CANCELLED') NOT NULL DEFAULT 'OPEN',
    assigned_to INT,
    resolved_at TIMESTAMP NULL,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to) REFERENCES employees(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS ticket_replies;
CREATE TABLE ticket_replies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    message TEXT NOT NULL,
    is_internal TINYINT(1) NOT NULL DEFAULT 0,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS knowledge_base;
CREATE TABLE knowledge_base (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    category VARCHAR(50),
    content TEXT,
    views INT NOT NULL DEFAULT 0,
    status TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- =====================================================
-- DOCUMENT MANAGEMENT
-- =====================================================

DROP TABLE IF EXISTS documents;
CREATE TABLE documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    doc_number VARCHAR(30) NOT NULL UNIQUE,
    title VARCHAR(200) NOT NULL,
    category VARCHAR(50),
    related_type VARCHAR(30),
    related_id INT,
    file_path VARCHAR(255),
    file_size INT,
    file_type VARCHAR(50),
    uploaded_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- =====================================================
-- EMAIL NOTIFICATION
-- =====================================================

DROP TABLE IF EXISTS email_settings;
CREATE TABLE email_settings (
    id INT PRIMARY KEY,
    smtp_host VARCHAR(100),
    smtp_port INT DEFAULT 587,
    smtp_user VARCHAR(100),
    smtp_pass VARCHAR(100),
    from_email VARCHAR(100),
    from_name VARCHAR(100),
    enabled TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB;

DROP TABLE IF EXISTS email_queue;
CREATE TABLE email_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    to_email VARCHAR(100) NOT NULL,
    subject VARCHAR(200) NOT NULL,
    body TEXT,
    status ENUM('PENDING','SENT','FAILED') NOT NULL DEFAULT 'PENDING',
    sent_at TIMESTAMP NULL,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================
-- MULTI-CURRENCY
-- =====================================================

DROP TABLE IF EXISTS currencies;
CREATE TABLE currencies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(3) NOT NULL UNIQUE,
    name VARCHAR(50) NOT NULL,
    symbol VARCHAR(5),
    exchange_rate DECIMAL(15,4) NOT NULL DEFAULT 1.0000,
    is_base TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================
-- BARCODE
-- =====================================================

DROP TABLE IF EXISTS barcodes;
CREATE TABLE barcodes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    barcode VARCHAR(100) NOT NULL UNIQUE,
    barcode_type ENUM('EAN13','CODE128','QR') NOT NULL DEFAULT 'CODE128',
    is_primary TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- APPROVAL MATRIX
-- =====================================================

DROP TABLE IF EXISTS approval_rules;
CREATE TABLE approval_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    doc_type VARCHAR(30) NOT NULL,
    min_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    max_amount DECIMAL(15,2) DEFAULT NULL,
    level INT NOT NULL DEFAULT 1,
    approver_role VARCHAR(20) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================
-- INTERCOMPANY
-- =====================================================

DROP TABLE IF EXISTS intercompany_transactions;
CREATE TABLE intercompany_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    trx_number VARCHAR(30) NOT NULL UNIQUE,
    from_branch INT NOT NULL,
    to_branch INT NOT NULL,
    trx_date DATE NOT NULL,
    description VARCHAR(255),
    amount DECIMAL(15,2) NOT NULL,
    status ENUM('DRAFT','CONFIRMED','CANCELLED') NOT NULL DEFAULT 'DRAFT',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (from_branch) REFERENCES branches(id),
    FOREIGN KEY (to_branch) REFERENCES branches(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- =====================================================
-- FORECASTING & MRP
-- =====================================================

DROP TABLE IF EXISTS sales_forecasts;
CREATE TABLE sales_forecasts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    period VARCHAR(7) NOT NULL, -- YYYY-MM
    forecast_qty DECIMAL(15,2) NOT NULL,
    actual_qty DECIMAL(15,2) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_forecast (product_id, period),
    FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS mrp_suggestions;
CREATE TABLE mrp_suggestions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    suggested_qty DECIMAL(15,2) NOT NULL,
    reason VARCHAR(255),
    status ENUM('PENDING','ORDERED','IGNORED') NOT NULL DEFAULT 'PENDING',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;

-- =====================================================
-- BI / ANALYTICS
-- =====================================================

DROP TABLE IF EXISTS dashboard_widgets;
CREATE TABLE dashboard_widgets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    widget_type VARCHAR(50) NOT NULL,
    widget_title VARCHAR(100),
    position INT NOT NULL DEFAULT 0,
    is_visible TINYINT(1) NOT NULL DEFAULT 1,
    config JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;
