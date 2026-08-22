-- =====================================================
-- MiniERP v3.0 (Enterprise) - Expansion Migration
-- =====================================================

SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================
-- HR & PAYROLL
-- =====================================================

DROP TABLE IF EXISTS departments;
CREATE TABLE departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    parent_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES departments(id) ON DELETE SET NULL
) ENGINE=InnoDB;

DROP TABLE IF EXISTS positions;
CREATE TABLE positions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    base_salary DECIMAL(15,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

DROP TABLE IF EXISTS employees;
CREATE TABLE employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_number VARCHAR(30) NOT NULL UNIQUE,
    full_name VARCHAR(150) NOT NULL,
    department_id INT,
    position_id INT,
    email VARCHAR(100),
    phone VARCHAR(30),
    hire_date DATE,
    base_salary DECIMAL(15,2) NOT NULL DEFAULT 0,
    bank_account VARCHAR(50),
    status ENUM('ACTIVE','RESIGNED','SUSPENDED') NOT NULL DEFAULT 'ACTIVE',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    FOREIGN KEY (position_id) REFERENCES positions(id) ON DELETE SET NULL
) ENGINE=InnoDB;

DROP TABLE IF EXISTS attendances;
CREATE TABLE attendances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    attend_date DATE NOT NULL,
    check_in TIME,
    check_out TIME,
    status ENUM('PRESENT','SICK','PERMIT','ABSENT','LEAVE') NOT NULL DEFAULT 'PRESENT',
    notes VARCHAR(255),
    UNIQUE KEY uq_attend (employee_id, attend_date),
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB;

DROP TABLE IF EXISTS payrolls;
CREATE TABLE payrolls (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payroll_number VARCHAR(30) NOT NULL UNIQUE,
    period VARCHAR(7) NOT NULL, -- YYYY-MM
    payroll_date DATE NOT NULL,
    total DECIMAL(15,2) NOT NULL DEFAULT 0,
    status ENUM('DRAFT','APPROVED','PAID','CANCELLED') NOT NULL DEFAULT 'DRAFT',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS payroll_items;
CREATE TABLE payroll_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payroll_id INT NOT NULL,
    employee_id INT NOT NULL,
    base_salary DECIMAL(15,2) NOT NULL DEFAULT 0,
    allowances DECIMAL(15,2) NOT NULL DEFAULT 0,
    deductions DECIMAL(15,2) NOT NULL DEFAULT 0,
    net_salary DECIMAL(15,2) NOT NULL DEFAULT 0,
    notes VARCHAR(255),
    FOREIGN KEY (payroll_id) REFERENCES payrolls(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employees(id)
) ENGINE=InnoDB;

-- =====================================================
-- MANUFACTURING (PP)
-- =====================================================

DROP TABLE IF EXISTS bom_items; -- Bill of Materials
CREATE TABLE bom_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,       -- finished good
    component_id INT NOT NULL,     -- raw material
    qty DECIMAL(15,4) NOT NULL,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (component_id) REFERENCES products(id)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS work_orders;
CREATE TABLE work_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    wo_number VARCHAR(30) NOT NULL UNIQUE,
    product_id INT NOT NULL,
    qty_plan DECIMAL(15,2) NOT NULL,
    qty_done DECIMAL(15,2) NOT NULL DEFAULT 0,
    planned_date DATE NOT NULL,
    status ENUM('PLANNED','IN_PROGRESS','COMPLETED','CANCELLED') NOT NULL DEFAULT 'PLANNED',
    notes VARCHAR(255),
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS work_order_components;
CREATE TABLE work_order_components (
    id INT AUTO_INCREMENT PRIMARY KEY,
    wo_id INT NOT NULL,
    product_id INT NOT NULL,  -- material
    qty_needed DECIMAL(15,2) NOT NULL,
    qty_issued DECIMAL(15,2) NOT NULL DEFAULT 0,
    FOREIGN KEY (wo_id) REFERENCES work_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;

-- =====================================================
-- WMS: Warehouse & Transfer
-- =====================================================

DROP TABLE IF EXISTS warehouses;
CREATE TABLE warehouses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    address VARCHAR(255),
    default_flag TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

DROP TABLE IF EXISTS stock_transfers;
CREATE TABLE stock_transfers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transfer_number VARCHAR(30) NOT NULL UNIQUE,
    from_warehouse INT NOT NULL,
    to_warehouse INT NOT NULL,
    transfer_date DATE NOT NULL,
    status ENUM('DRAFT','CONFIRMED','CANCELLED') NOT NULL DEFAULT 'DRAFT',
    notes VARCHAR(255),
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (from_warehouse) REFERENCES warehouses(id),
    FOREIGN KEY (to_warehouse) REFERENCES warehouses(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS stock_transfer_items;
CREATE TABLE stock_transfer_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transfer_id INT NOT NULL,
    product_id INT NOT NULL,
    qty DECIMAL(15,2) NOT NULL,
    FOREIGN KEY (transfer_id) REFERENCES stock_transfers(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;

-- product stock per warehouse
DROP TABLE IF EXISTS warehouse_stocks;
CREATE TABLE warehouse_stocks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    warehouse_id INT NOT NULL,
    product_id INT NOT NULL,
    qty DECIMAL(15,2) NOT NULL DEFAULT 0,
    UNIQUE KEY uq_stock (warehouse_id, product_id),
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- PROJECT MANAGEMENT
-- =====================================================

DROP TABLE IF EXISTS projects;
CREATE TABLE projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(30) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    customer_id INT,
    start_date DATE,
    end_date DATE,
    budget DECIMAL(15,2) NOT NULL DEFAULT 0,
    status ENUM('PLANNING','ACTIVE','ONHOLD','COMPLETED','CANCELLED') NOT NULL DEFAULT 'PLANNING',
    description TEXT,
    manager_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    FOREIGN KEY (manager_id) REFERENCES employees(id) ON DELETE SET NULL
) ENGINE=InnoDB;

DROP TABLE IF EXISTS project_tasks;
CREATE TABLE project_tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    assignee_id INT,
    due_date DATE,
    priority ENUM('LOW','MEDIUM','HIGH','CRITICAL') NOT NULL DEFAULT 'MEDIUM',
    status ENUM('TODO','IN_PROGRESS','DONE','CANCELLED') NOT NULL DEFAULT 'TODO',
    progress TINYINT NOT NULL DEFAULT 0,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (assignee_id) REFERENCES employees(id) ON DELETE SET NULL
) ENGINE=InnoDB;

DROP TABLE IF EXISTS project_timesheets;
CREATE TABLE project_timesheets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    task_id INT,
    employee_id INT NOT NULL,
    work_date DATE NOT NULL,
    hours DECIMAL(5,2) NOT NULL,
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (task_id) REFERENCES project_tasks(id) ON DELETE SET NULL,
    FOREIGN KEY (employee_id) REFERENCES employees(id)
) ENGINE=InnoDB;

-- =====================================================
-- ASSETS
-- =====================================================

DROP TABLE IF EXISTS assets;
CREATE TABLE assets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(30) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    category VARCHAR(50),
    purchase_date DATE,
    purchase_value DECIMAL(15,2) NOT NULL DEFAULT 0,
    salvage_value DECIMAL(15,2) NOT NULL DEFAULT 0,
    useful_life_years INT NOT NULL DEFAULT 4,
    method ENUM('STRAIGHT_LINE') NOT NULL DEFAULT 'STRAIGHT_LINE',
    status ENUM('ACTIVE','DISPOSED') NOT NULL DEFAULT 'ACTIVE',
    location VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

DROP TABLE IF EXISTS asset_depreciations;
CREATE TABLE asset_depreciations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asset_id INT NOT NULL,
    period VARCHAR(7) NOT NULL, -- YYYY-MM
    amount DECIMAL(15,2) NOT NULL,
    posted TINYINT(1) NOT NULL DEFAULT 0,
    journal_id INT NULL,
    UNIQUE KEY uq_depr (asset_id, period),
    FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE,
    FOREIGN KEY (journal_id) REFERENCES journal_entries(id)
) ENGINE=InnoDB;

-- =====================================================
-- QUALITY CONTROL
-- =====================================================

DROP TABLE IF EXISTS qc_inspections;
CREATE TABLE qc_inspections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    inspection_number VARCHAR(30) NOT NULL UNIQUE,
    reference_type ENUM('PURCHASE','SALES','PRODUCTION') NOT NULL,
    reference_id INT NOT NULL,
    inspection_date DATE NOT NULL,
    inspector_id INT,
    result ENUM('PENDING','PASSED','FAILED','PARTIAL') NOT NULL DEFAULT 'PENDING',
    notes VARCHAR(255),
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (inspector_id) REFERENCES employees(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS qc_inspection_items;
CREATE TABLE qc_inspection_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    inspection_id INT NOT NULL,
    product_id INT NOT NULL,
    qty_checked DECIMAL(15,2) NOT NULL,
    qty_passed DECIMAL(15,2) NOT NULL DEFAULT 0,
    qty_failed DECIMAL(15,2) NOT NULL DEFAULT 0,
    remark VARCHAR(255),
    FOREIGN KEY (inspection_id) REFERENCES qc_inspections(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;

-- =====================================================
-- BUDGET
-- =====================================================

DROP TABLE IF EXISTS budgets;
CREATE TABLE budgets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    fiscal_year INT NOT NULL,
    department_id INT,
    status ENUM('DRAFT','ACTIVE','CLOSED') NOT NULL DEFAULT 'DRAFT',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS budget_lines;
CREATE TABLE budget_lines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    budget_id INT NOT NULL,
    account_id INT NOT NULL,
    month INT NOT NULL, -- 1-12
    amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    FOREIGN KEY (budget_id) REFERENCES budgets(id) ON DELETE CASCADE,
    FOREIGN KEY (account_id) REFERENCES accounts(id)
) ENGINE=InnoDB;

-- =====================================================
-- COMPANY & SETTINGS
-- =====================================================

DROP TABLE IF EXISTS company_settings;
CREATE TABLE company_settings (
    id INT PRIMARY KEY,
    company_name VARCHAR(150) NOT NULL DEFAULT 'PT Mitra Sejahtera',
    address TEXT,
    phone VARCHAR(30),
    email VARCHAR(100),
    tax_id VARCHAR(50),        -- NPWP
    logo VARCHAR(255),
    currency VARCHAR(10) DEFAULT 'Rp',
    date_format VARCHAR(20) DEFAULT 'd/m/Y'
) ENGINE=InnoDB;

DROP TABLE IF EXISTS document_numbering;
CREATE TABLE document_numbering (
    id INT AUTO_INCREMENT PRIMARY KEY,
    doc_type VARCHAR(30) NOT NULL UNIQUE,
    prefix VARCHAR(10) NOT NULL,
    last_number INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================
-- APPROVAL WORKFLOW
-- =====================================================

DROP TABLE IF EXISTS approval_flows;
CREATE TABLE approval_flows (
    id INT AUTO_INCREMENT PRIMARY KEY,
    doc_type VARCHAR(30) NOT NULL,  -- PURCHASE, SALES, TRANSFER, PAYROLL
    level INT NOT NULL DEFAULT 1,
    role VARCHAR(20) NOT NULL,      -- admin/manager/staff
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

DROP TABLE IF EXISTS document_approvals;
CREATE TABLE document_approvals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    doc_type VARCHAR(30) NOT NULL,
    doc_id INT NOT NULL,
    level INT NOT NULL,
    approved_by INT,
    approved_at TIMESTAMP NULL,
    status ENUM('PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING',
    notes VARCHAR(255),
    FOREIGN KEY (approved_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- =====================================================
-- POS (Point of Sale)
-- =====================================================

DROP TABLE IF EXISTS pos_sessions;
CREATE TABLE pos_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_number VARCHAR(30) NOT NULL UNIQUE,
    opened_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    opened_by INT,
    opening_cash DECIMAL(15,2) NOT NULL DEFAULT 0,
    closing_cash DECIMAL(15,2) NULL,
    closed_at TIMESTAMP NULL,
    status ENUM('OPEN','CLOSED') NOT NULL DEFAULT 'OPEN',
    FOREIGN KEY (opened_by) REFERENCES users(id)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS pos_transactions;
CREATE TABLE pos_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    trx_number VARCHAR(30) NOT NULL UNIQUE,
    session_id INT NOT NULL,
    customer_id INT NULL,
    trx_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    total DECIMAL(15,2) NOT NULL DEFAULT 0,
    paid DECIMAL(15,2) NOT NULL DEFAULT 0,
    change_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    payment_method VARCHAR(30) DEFAULT 'CASH',
    created_by INT,
    FOREIGN KEY (session_id) REFERENCES pos_sessions(id),
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS pos_transaction_items;
CREATE TABLE pos_transaction_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    trx_id INT NOT NULL,
    product_id INT NOT NULL,
    qty DECIMAL(15,2) NOT NULL,
    price DECIMAL(15,2) NOT NULL,
    subtotal DECIMAL(15,2) NOT NULL,
    FOREIGN KEY (trx_id) REFERENCES pos_transactions(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;
