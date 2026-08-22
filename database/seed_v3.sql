-- =====================================================
-- MiniERP v3.0 - Seed Data Modul Baru
-- =====================================================

-- Departments
INSERT INTO departments (code, name) VALUES
('DIR', 'Direksi'),
('OPS', 'Operasional'),
('FIN', 'Keuangan'),
('HRD', 'HR & GA'),
('SAL', 'Sales & Marketing'),
('PRD', 'Produksi'),
('LOG', 'Logistik & Gudang');

-- Positions
INSERT INTO positions (code, name, base_salary) VALUES
('GM', 'General Manager', 15000000),
('MGR', 'Manager', 10000000),
('SPV', 'Supervisor', 6500000),
('STF', 'Staff', 4500000),
('ADM', 'Admin', 4000000),
('OPR', 'Operator Produksi', 3800000),
('KSR', 'Kasir', 3500000);

-- Employees
INSERT INTO employees (employee_number, full_name, department_id, position_id, email, phone, hire_date, base_salary) VALUES
('EMP-001', 'Budi Hartono', 1, 1, 'budi.h@mitra.co.id', '08120000001', '2015-01-10', 15000000),
('EMP-002', 'Siti Rahma', 3, 2, 'siti.r@mitra.co.id', '08120000002', '2017-03-15', 10000000),
('EMP-003', 'Andi Wijaya', 5, 3, 'andi.w@mitra.co.id', '08120000003', '2018-07-01', 6500000),
('EMP-004', 'Dewi Lestari', 6, 6, 'dewi.l@mitra.co.id', '08120000004', '2019-02-11', 3800000),
('EMP-005', 'Rudi Santoso', 7, 4, 'rudi.s@mitra.co.id', '08120000005', '2019-09-01', 4500000),
('EMP-006', 'Nina Kurnia', 2, 4, 'nina.k@mitra.co.id', '08120000006', '2020-05-18', 4500000),
('EMP-007', 'Agus Prasetyo', 7, 7, 'agus.p@mitra.co.id', '08120000007', '2021-01-04', 3500000),
('EMP-008', 'Maya Sari', 4, 5, 'maya.s@mitra.co.id', '08120000008', '2021-11-22', 4000000);

-- Warehouses
INSERT INTO warehouses (code, name, address, default_flag) VALUES
('WH-UTM', 'Gudang Utama', 'Jl. Raya Industri No. 10, Jakarta Timur', 1),
('WH-BDG', 'Gudang Bandung', 'Jl. Cibaduyut No. 88, Bandung', 0),
('WH-SBY', 'Gudang Surabaya', 'Jl. Rungkut Industri II, Surabaya', 0);

-- BOM: Meja Kerja Kayu Jati butuh bahan baku
INSERT INTO bom_items (product_id, component_id, qty) VALUES
(10, 8, 2),    -- 1 meja butuh 2 batang baja ringan (kerangka)
(10, 9, 1);    -- 1 meja butuh 1 sak semen (campuran finising, contoh)

-- Company settings
INSERT INTO company_settings (id, company_name, address, phone, email, tax_id) VALUES
(1, 'PT Mitra Sejahtera Indonesia', 'Jl. Sudirman Kav. 25, Jakarta Selatan', '021-5550000', 'info@mitrasejahtera.co.id', '01.234.567.8-901.000');

-- Document numbering presets
INSERT INTO document_numbering (doc_type, prefix) VALUES
('PO', 'PO'), ('SO', 'SO'), ('DO', 'DO'), ('SI', 'SI'), ('PI', 'PI'),
('RCV', 'RCV'), ('PAY', 'PAY'), ('SR', 'SR'), ('PR', 'PR'),
('OPN', 'OPN'), ('JE', 'JE'), ('WO', 'WO'), ('TRF', 'TRF'),
('QC', 'QC'), ('PRL', 'PRL'), ('POS', 'POS'), ('INV', 'INV');

-- Approval flow defaults
INSERT INTO approval_flows (doc_type, level, role) VALUES
('PURCHASE', 1, 'manager'),
('PURCHASE', 2, 'admin'),
('SALES', 1, 'manager'),
('TRANSFER', 1, 'manager'),
('PAYROLL', 1, 'admin');

-- Sample project
INSERT INTO projects (code, name, customer_id, start_date, end_date, budget, status, description, manager_id) VALUES
('PRJ-001', 'Implementasi Sistem Inventory CV Sejahtera', 2, '2026-08-01', '2026-10-31', 50000000, 'ACTIVE', 'Implementasi modul inventory & gudang', 3);

INSERT INTO project_tasks (project_id, title, assignee_id, due_date, priority, status, progress) VALUES
(1, 'Setup master data produk', 6, '2026-08-25', 'HIGH', 'DONE', 100),
(1, 'Konfigurasi gudang & lokasi', 6, '2026-08-30', 'HIGH', 'IN_PROGRESS', 60),
(1, 'UAT dengan user client', 3, '2026-09-15', 'MEDIUM', 'TODO', 0),
(1, 'Go-live & dokumentasi', 3, '2026-10-20', 'CRITICAL', 'TODO', 0);

-- Assets
INSERT INTO assets (code, name, category, purchase_date, purchase_value, salvage_value, useful_life_years, location) VALUES
('AST-001', 'Forklift Toyota 3 Ton', 'Kendaraan', '2023-05-10', 250000000, 50000000, 8, 'WH-UTM'),
('AST-002', 'Mesin CNC Router', 'Mesin Produksi', '2022-11-01', 180000000, 20000000, 10, 'PRD'),
('AST-003', 'Komputer Server Dell R750', 'IT Equipment', '2024-02-14', 85000000, 5000000, 5, 'IT Room'),
('AST-004', 'Mobil Operasional Innova', 'Kendaraan', '2021-08-20', 320000000, 100000000, 8, 'Kantor Pusat');

-- Accounts tambahan untuk modul baru
INSERT INTO accounts (code, name, type) VALUES
('1-1400', 'Piutang Karyawan', 'ASSET'),
('1-1500', 'Aktiva Tetap', 'ASSET'),
('1-1510', 'Akumulasi Penyusutan', 'ASSET'),
('2-1200', 'Hutang Gaji', 'LIABILITY'),
('5-1300', 'Biaya Penyusutan', 'EXPENSE'),
('5-1400', 'Biaya Produksi', 'EXPENSE'),
('5-1500', 'Biaya Proyek', 'EXPENSE');

-- Attendance sample (bulan berjalan)
INSERT INTO attendances (employee_id, attend_date, check_in, check_out, status) VALUES
(3, CURDATE() - INTERVAL 2 DAY, '08:02:00', '17:05:00', 'PRESENT'),
(3, CURDATE() - INTERVAL 1 DAY, '07:58:00', '17:10:00', 'PRESENT'),
(3, CURDATE(), '08:15:00', NULL, 'PRESENT'),
(6, CURDATE() - INTERVAL 2 DAY, '08:00:00', '17:00:00', 'PRESENT'),
(6, CURDATE() - INTERVAL 1 DAY, NULL, NULL, 'SICK');
