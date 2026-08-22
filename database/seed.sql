-- =====================================================
-- MiniERP - Seed / Demo Data
-- Login: admin / admin123
-- =====================================================

-- Users
INSERT INTO users (username, password, full_name, email, role, status) VALUES
('admin', '$2y$12$KO4vSnT1t34zEKScT0CG7.g7v/KWGkm9a/7suSa6bHE6Kd432aOS2', 'Administrator', 'admin@erp.local', 'admin', 1),
('manager', '$2y$12$KO4vSnT1t34zEKScT0CG7.g7v/KWGkm9a/7suSa6bHE6Kd432aOS2', 'Manager Operasional', 'manager@erp.local', 'manager', 1),
('staff', '$2y$12$KO4vSnT1t34zEKScT0CG7.g7v/KWGkm9a/7suSa6bHE6Kd432aOS2', 'Staff Gudang', 'staff@erp.local', 'staff', 1);

-- Chart of Accounts
INSERT INTO accounts (code, name, type) VALUES
('1-1000', 'Kas', 'ASSET'),
('1-1100', 'Bank', 'ASSET'),
('1-1200', 'Piutang Usaha', 'ASSET'),
('1-1300', 'Persediaan Barang', 'ASSET'),
('2-1000', 'Hutang Usaha', 'LIABILITY'),
('2-1100', 'Hutang Pajak', 'LIABILITY'),
('3-1000', 'Modal', 'EQUITY'),
('3-1100', 'Laba Ditahan', 'EQUITY'),
('4-1000', 'Pendapatan Penjualan', 'REVENUE'),
('5-1000', 'Harga Pokok Penjualan', 'EXPENSE'),
('5-1100', 'Biaya Operasional', 'EXPENSE'),
('5-1200', 'Biaya Gaji', 'EXPENSE');

-- Categories
INSERT INTO categories (code, name, description) VALUES
('ELK', 'Elektronik', 'Produk elektronik dan perangkat'),
('F&B', 'Makanan & Minuman', 'Produk konsumsi'),
('ATK', 'Alat Tulis Kantor', 'Perlengkapan kantor'),
('RWT', 'Raw Material', 'Bahan baku produksi'),
('FNS', 'Finished Goods', 'Barang jadi siap jual');

-- Products
INSERT INTO products (code, name, category_id, unit, purchase_price, selling_price, stock, min_stock) VALUES
('PRD-001', 'Laptop Lenovo ThinkPad', 1, 'UNIT', 7500000, 9500000, 15, 3),
('PRD-002', 'Mouse Wireless Logitech', 1, 'PCS', 85000, 150000, 120, 20),
('PRD-003', 'Keyboard Mechanical', 1, 'PCS', 250000, 425000, 45, 10),
('PRD-004', 'Kopi Arabica 1kg', 2, 'KG', 120000, 180000, 80, 15),
('PRD-005', 'Teh Premium Box', 2, 'BOX', 35000, 55000, 200, 50),
('PRD-006', 'Kertas HVS A4 80gr', 3, 'RIM', 42000, 58000, 300, 50),
('PRD-007', 'Pulpen Standard', 3, 'LSN', 24000, 42000, 100, 20),
('PRD-008', 'Baja Ringan 0.75mm', 4, 'BTG', 95000, 125000, 500, 100),
('PRD-009', 'Semen Portland 50kg', 4, 'SAK', 62000, 78000, 250, 50),
('PRD-010', 'Meja Kerja Kayu Jati', 5, 'UNIT', 1500000, 2400000, 8, 2);

-- Customers
INSERT INTO customers (code, name, email, phone, address, city) VALUES
('CUST-001', 'PT Maju Bersama', 'purchasing@majubersama.co.id', '021-5551234', 'Jl. Sudirman No. 45', 'Jakarta'),
('CUST-002', 'CV Sejahtera Abadi', 'order@sejahtera.com', '031-8889999', 'Jl. Pemuda No. 12', 'Surabaya'),
('CUST-003', 'Toko Sinar Jaya', 'sinarjaya@mail.com', '022-7771234', 'Jl. Asia Afrika No. 8', 'Bandung'),
('CUST-004', 'UD Berkah Mandiri', 'berkah@mail.com', '0274-666777', 'Jl. Malioboro No. 99', 'Yogyakarta'),
('CUST-005', 'PT Global Nusantara', 'procurement@globalnus.co.id', '021-3334444', 'Jl. Thamrin No. 21', 'Jakarta');

-- Suppliers
INSERT INTO suppliers (code, name, email, phone, address) VALUES
('SUP-001', 'PT Distribusi Tekno', 'sales@disttekno.com', '021-9998888', 'Jl. Gatot Subroto Kav. 5, Jakarta'),
('SUP-002', 'CV Sumber Pangan', 'order@sumberpangan.com', '022-1112222', 'Jl. Cibaduyut No. 77, Bandung'),
('SUP-003', 'PT Kertas Nusantara', 'sales@kertasnus.co.id', '031-4445555', 'Jl. Rungkut Industri IV, Surabaya'),
('SUP-004', 'UD Baja Prima', 'bajaprima@mail.com', '0274-888999', 'Jl. Magelang KM 10, Yogyakarta');

-- Sample Purchase Orders
INSERT INTO purchase_orders (po_number, supplier_id, order_date, status, total, notes, created_by) VALUES
('PO-2026-0001', 1, '2026-07-15', 'RECEIVED', 79000000, 'Pengadaan laptop & aksesoris', 1),
('PO-2026-0002', 4, '2026-07-20', 'RECEIVED', 25000000, 'Restock material bangunan', 1),
('PO-2026-0003', 2, '2026-08-01', 'APPROVED', 5600000, 'Pengadaan F&B bulanan', 1),
('PO-2026-0004', 3, '2026-08-10', 'DRAFT', 4200000, 'Restock ATK', 1);

INSERT INTO purchase_order_items (po_id, product_id, qty, price, subtotal) VALUES
(1, 1, 10, 7500000, 75000000),
(1, 2, 40, 85000, 3400000),
(1, 3, 6, 250000, 1500000),
(2, 8, 150, 95000, 14250000),
(2, 9, 150, 62000, 9300000),
(3, 4, 30, 120000, 3600000),
(3, 5, 40, 35000, 1400000),
(4, 6, 100, 42000, 4200000);

-- Sample Sales Orders
INSERT INTO sales_orders (so_number, customer_id, order_date, status, total, notes, created_by) VALUES
('SO-2026-0001', 1, '2026-07-18', 'DELIVERED', 19700000, 'Order laptop PT Maju Bersama', 1),
('SO-2026-0002', 3, '2026-07-25', 'DELIVERED', 1160000, 'Order ATK rutin', 1),
('SO-2026-0003', 2, '2026-08-05', 'CONFIRMED', 8750000, 'Order material CV Sejahtera', 1),
('SO-2026-0004', 5, '2026-08-12', 'DRAFT', 4250000, 'Draft order aksesoris', 1);

INSERT INTO sales_order_items (so_id, product_id, qty, price, subtotal) VALUES
(1, 1, 2, 9500000, 19000000),
(1, 2, 5, 140000, 700000),
(2, 6, 20, 58000, 1160000),
(3, 8, 50, 125000, 6250000),
(3, 9, 25, 78000, 1950000),
(4, 2, 10, 150000, 1500000),
(4, 3, 10, 275000, 2750000);

-- Sample Stock Movements
INSERT INTO stock_movements (product_id, movement_type, qty, reference_type, reference_id, notes, created_by) VALUES
(1, 'IN', 10, 'PURCHASE', 1, 'Penerimaan PO-2026-0001', 1),
(2, 'IN', 40, 'PURCHASE', 1, 'Penerimaan PO-2026-0001', 1),
(3, 'IN', 6, 'PURCHASE', 1, 'Penerimaan PO-2026-0001', 1),
(8, 'IN', 150, 'PURCHASE', 2, 'Penerimaan PO-2026-0002', 1),
(9, 'IN', 150, 'PURCHASE', 2, 'Penerimaan PO-2026-0002', 1),
(1, 'OUT', 2, 'SALES', 1, 'Pengiriman SO-2026-0001', 1),
(2, 'OUT', 5, 'SALES', 1, 'Pengiriman SO-2026-0001', 1),
(6, 'OUT', 20, 'SALES', 2, 'Pengiriman SO-2026-0002', 1),
(8, 'OUT', 50, 'SALES', 3, 'Pengiriman SO-2026-0003', 1),
(9, 'OUT', 25, 'SALES', 3, 'Pengiriman SO-2026-0003', 1);

-- Sample Journal Entries
INSERT INTO journal_entries (entry_number, entry_date, description, reference, created_by) VALUES
('JE-2026-0001', '2026-07-15', 'Pembelian barang PO-2026-0001', 'PO-2026-0001', 1),
('JE-2026-0002', '2026-07-18', 'Penjualan SO-2026-0001', 'SO-2026-0001', 1),
('JE-2026-0003', '2026-07-31', 'Pembayaran gaji karyawan Juli', 'PAYROLL-0726', 1);

INSERT INTO journal_entry_lines (journal_id, account_id, debit, credit) VALUES
(1, 4, 79000000, 0),
(1, 5, 0, 79000000),
(2, 3, 19700000, 0),
(2, 9, 0, 19700000),
(2, 10, 15750000, 0),
(2, 4, 0, 15750000),
(3, 12, 25000000, 0),
(3, 1, 0, 25000000);
