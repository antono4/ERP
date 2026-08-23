-- =====================================================
-- MiniERP v4.0 - Seed Data Modul Baru
-- =====================================================

-- Taxes
INSERT INTO taxes (code, name, type, rate) VALUES
('PPN', 'Pajak Pertambahan Nilai', 'PPN', 11.00),
('PPh21', 'PPh Pasal 21 (Karyawan)', 'PPH21', 5.00),
('PPh22', 'PPh Pasal 22 (Impor)', 'PPH22', 2.50),
('PPh23', 'PPh Pasal 23 (Jasa)', 'PPH23', 2.00),
('PPh4_2', 'PPh Pasal 4 ayat 2', 'PPH4_2', 4.00);

-- Branches
INSERT INTO branches (code, name, address, is_head_office) VALUES
('HQ-JKT', 'Kantor Pusat Jakarta', 'Jl. Sudirman Kav. 25, Jakarta', 1),
('BR-BDG', 'Cabang Bandung', 'Jl. Cibaduyut No. 88, Bandung', 0),
('BR-SBY', 'Cabang Surabaya', 'Jl. Rungkut Industri II, Surabaya', 0);

-- Carriers
INSERT INTO carriers (code, name, type) VALUES
('JNE', 'JNE Express', 'COURIER'),
('SJT', 'Samudera Jaya Transport', 'TRUCK'),
('GMS', 'Global Maritime Shipping', 'SHIP'),
('AEX', 'Air Express Indonesia', 'AIR');

-- Currencies
INSERT INTO currencies (code, name, symbol, exchange_rate, is_base) VALUES
('IDR', 'Rupiah Indonesia', 'Rp', 1.0000, 1),
('USD', 'US Dollar', '$', 15850.00, 0),
('SGD', 'Singapore Dollar', 'S$', 11800.00, 0),
('EUR', 'Euro', '€', 17200.00, 0),
('JPY', 'Japanese Yen', '¥', 105.00, 0);

-- Email settings
INSERT INTO email_settings (id, smtp_host, smtp_port, smtp_user, from_email, from_name, enabled) VALUES
(1, 'smtp.gmail.com', 587, 'noreply@mitra.co.id', 'noreply@mitra.co.id', 'MiniERP System', 0);

-- Commission rules
INSERT INTO commission_rules (name, type, base_value, min_amount) VALUES
('Sales Standard', 'PERCENTAGE', 2.5, 0),
('Sales Premium Product', 'PERCENTAGE', 5.0, 10000000),
('Sales Tiered', 'TIERED', 1.5, 50000000);

-- Sample lead
INSERT INTO leads (lead_number, company_name, contact_name, email, phone, source, status, estimated_value, assigned_to) VALUES
('LED-001', 'PT New Vision Tech', 'Bpk. Johnson', 'johnson@nvt.co.id', '021-8887777', 'Website', 'QUALIFIED', 500000000, 3);

-- Sample opportunity
INSERT INTO opportunities (opp_number, lead_id, customer_id, name, stage, probability, expected_value, expected_close_date, assigned_to) VALUES
('OPP-001', 1, NULL, 'Implementasi ERP PT New Vision', 'PROPOSAL', 60, 500000000, '2026-09-30', 3);

-- Knowledge base sample
INSERT INTO knowledge_base (title, category, content) VALUES
('Cara membuat PO', 'Purchasing', 'Buka menu Purchasing > Buat PO Baru, pilih supplier, tambahkan item, simpan.'),
('Proses retur penjualan', 'Sales', 'Buka menu Retur > Retur Penjualan, pilih SO yang sudah delivered, isi qty retur, konfirmasi.'),
('Input absensi karyawan', 'HR', 'Buka menu Absensi, pilih karyawan, tanggal, jam masuk/keluar, status, simpan.');

-- Approval rules
INSERT INTO approval_rules (doc_type, min_amount, max_amount, level, approver_role) VALUES
('PURCHASE', 0, 50000000, 1, 'manager'),
('PURCHASE', 50000000, NULL, 2, 'admin'),
('SALES', 0, 100000000, 1, 'manager'),
('PAYROLL', 0, NULL, 1, 'admin');
