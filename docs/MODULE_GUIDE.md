# 📗 Panduan Modul MiniERP v4.0 (Teknis)

Dokumentasi teknis setiap modul: tabel database, workflow, integrasi, dan file kode.

---

## Daftar Modul

| # | Modul | Kode SAP-Equivalent | Tabel Utama | File |
|---|---|---|---|---|
| 1 | Dashboard | — | — | `modules/dashboard/index.php` |
| 2 | Master Data | MM-BP | products, categories, customers, suppliers, users | `modules/master/` |
| 3 | Purchasing | MM-PUR | purchase_orders, purchase_order_items | `modules/purchasing/` |
| 4 | Sales | SD | sales_orders, sales_order_items | `modules/sales/` |
| 5 | Delivery | SD-LE | delivery_orders, delivery_order_items | `modules/delivery/` |
| 6 | Returns | MM/SD-RET | sales_returns, purchase_returns | `modules/returns/` |
| 7 | Billing (AR/AP) | FI-AR/AP | sales_invoices, purchase_invoices, payments | `modules/billing/` |
| 8 | Inventory | MM-IM | stock_movements, stock_opnames | `modules/inventory/` |
| 9 | Finance | FI-GL | accounts, journal_entries, journal_entry_lines | `modules/finance/` |
| 10 | HR & Payroll | HCM | employees, departments, attendances, payrolls | `modules/hr/` |
| 11 | Manufacturing | PP | bom_items, work_orders | `modules/manufacturing/` |
| 12 | WMS | WM | warehouses, stock_transfers, warehouse_stocks | `modules/wms/` |
| 13 | Project | PS | projects, project_tasks, project_timesheets | `modules/projects/` |
| 14 | Assets | FI-AA | assets, asset_depreciations | `modules/assets/` |
| 15 | Quality Control | QM | qc_inspections, qc_inspection_items | `modules/qc/` |
| 16 | Budget | CO | budgets, budget_lines | `modules/budget/` |
| 17 | POS | — | pos_sessions, pos_transactions | `modules/pos/` |
| 18 | CRM | CRM | leads, opportunities, crm_activities | `modules/crm/` |
| 19 | Tax | FI-TAX | taxes, tax_transactions, e_faktur | `modules/tax/` |
| 20 | Branch | — | branches | `modules/branches/` |
| 21 | Shipment | LE-TRA | carriers, shipments | `modules/shipment/` |
| 22 | Landed Cost | MM-IV | landed_costs, landed_cost_allocations | `modules/cost/` |
| 23 | Commission | SD-CM | commission_rules, commission_transactions | `modules/commission/` |
| 24 | Service | CS | tickets, ticket_replies, knowledge_base | `modules/service/` |
| 25 | Documents | DMS | documents | `modules/documents/` |
| 26 | Currency | FI-CUR | currencies | `modules/currency/` |
| 27 | Approval | WF | approval_flows, approval_rules, document_approvals | `modules/approval/` |
| 28 | Forecast & MRP | PP-MRP | sales_forecasts, mrp_suggestions | `modules/forecast/` |
| 29 | REST API | — | — | `modules/api/` |
| 30 | System | — | activity_logs, company_settings | `modules/system/` |

---

## Arsitektur Sistem

```
index.php (Front Controller / Router)
    ├── config/config.php     — konfigurasi DB & app
    ├── core/
    │   ├── Database.php      — PDO wrapper (singleton)
    │   ├── Auth.php          — session & role management
    │   └── functions.php     — helper (money, date, numbering, log)
    ├── layouts/              — header, sidebar, footer (AdminLTE 3)
    └── modules/<modul>/      — satu file per halaman
        ├── module_handle()   — proses POST sebelum output
        ├── module_render()   — render HTML
        └── module_scripts()  — JS per halaman (opsional)
```

### Konvensi Modul

Setiap file modul mengikuti pola yang sama:

```php
<?php
$pageTitle = 'Nama Halaman';

function module_handle(): void {
    // 1. Proses aksi POST (create/update/delete/workflow)
    // 2. Set page title via $GLOBALS['pageTitle'] jika perlu
    // 3. Redirect setelah aksi (PRG pattern)
}

function module_render(): void {
    // Render HTML (Bootstrap 4 / AdminLTE)
}

function module_scripts(): void {
    // JS kustom halaman (jQuery tersedia)
}
```

### Helper Penting

| Fungsi | Fungsi |
|---|---|
| `money($val)` | Format Rupiah |
| `fdate($date)` | Format tanggal dd/mm/yyyy |
| `e($str)` | Escape HTML (anti-XSS) |
| `generateNumber($table, $col, $prefix)` | Nomor dokumen otomatis `PREFIX-YYYY-NNNN` |
| `statusBadge($status)` | Badge berwarna per status |
| `setFlash($type, $msg)` | Pesan notifikasi sekali tampil |
| `logActivity($module, $action, $desc)` | Audit trail |
| `Auth::requireRole(['admin'])` | Batasi akses per role |

---

## Workflow Dokumen

### Purchase Order
```
DRAFT → APPROVED → RECEIVED ─┬─ Stok + (stock_movements IN)
                             └─ Jurnal: Persediaan D / Hutang K
```

### Sales Order
```
DRAFT → CONFIRMED → DELIVERED ─┬─ Stok − (stock_movements OUT)
                               ├─ Jurnal: Piutang D / Pendapatan K
                               └─ Jurnal: HPP D / Persediaan K
```

### Surat Jalan (Partial Delivery)
```
SO CONFIRMED → DO SHIPPED (parsial OK) → semua item terkirim → SO DELIVERED
```

### Payroll
```
DRAFT (hitung dari absensi) → APPROVED → PAID ─→ Jurnal: Biaya Gaji D / Bank K
```

### Work Order
```
PLANNED → IN_PROGRESS → COMPLETED ─┬─ Bahan baku − (issue)
                                   └─ Barang jadi + (receipt)
```

### Stock Opname
```
OPEN (input fisik) → CONFIRMED ─→ Selisih disesuaikan via stock_movements ADJUSTMENT
```

---

## Integrasi Jurnal Otomatis

| Trigger | Jurnal Otomatis |
|---|---|
| PO Received | Persediaan D / Hutang Usaha K |
| SO Delivered | Piutang D / Pendapatan K + HPP D / Persediaan K |
| Pembayaran AR | Bank D / Piutang K |
| Pembayaran AP | Hutang D / Bank K |
| Retur Penjualan | Pendapatan D / Piutang K + Persediaan D / HPP K |
| Retur Pembelian | Hutang D / Persediaan K |
| Payroll Paid | Biaya Gaji D / Bank K |
| Depresiasi Posted | Biaya Penyusutan D / Akum. Penyusutan K |
| POS Sale | Kas D / Pendapatan K |
| Produksi Selesai | Persediaan D / Persediaan K (transfer nilai) |

---

## REST API

Base URL: `index.php?page=api&action=<action>&api_key=<key>`

| Endpoint | Method | Deskripsi |
|---|---|---|
| `products` | GET | Daftar produk |
| `product&id=X` | GET | Detail produk |
| `sales_orders&status=X` | GET | Daftar SO (filter status) |
| `stock` | GET | Posisi stok |
| `stock_movements&product_id=X` | GET | Kartu stok produk |
| `create_sales_order` | POST | Buat SO via API (JSON body) |
| `create_purchase_order` | POST | Buat PO via API (JSON body) |

**Contoh:**
```bash
curl "http://localhost/ERP/index.php?page=api&action=stock&api_key=YOUR_KEY"
```

---

## Keamanan

- **SQL Injection**: semua query pakai PDO prepared statements
- **XSS**: semua output di-escape dengan `e()` (htmlspecialchars)
- **Auth**: session-based, `password_hash()` / `password_verify()` (bcrypt)
- **CSRF**: form pakai POST + session check
- **Role**: admin / manager / staff dengan `requireRole()`

---

## Penomoran Dokumen Otomatis

| Prefix | Dokumen |
|---|---|
| PO | Purchase Order |
| SO | Sales Order |
| DO | Delivery Order |
| SI / PI | Sales / Purchase Invoice |
| RCV / PAY | Payment Receive / Pay |
| SR / PR | Sales / Purchase Return |
| OPN | Stock Opname |
| JE | Journal Entry |
| WO | Work Order |
| TRF | Stock Transfer |
| QC | QC Inspection |
| PRL | Payroll |
| POS | POS Transaction |
| LED / OPP | Lead / Opportunity |
| EF | e-Faktur |
| LC | Landed Cost |
| COM | Commission |
| TKT | Ticket |
| SHP | Shipment |
| DOC | Document |

---

## Instalasi Lengkap

```bash
# Clone
git clone https://github.com/antono4/ERP.git
cd ERP

# Database
mysql -u root -e "CREATE DATABASE erp_db"
mysql -u root erp_db < database/schema.sql
mysql -u root erp_db < database/seed.sql
mysql -u root erp_db < database/migration_v2.sql
mysql -u root erp_db < database/migration_v3.sql
mysql -u root erp_db < database/seed_v3.sql
mysql -u root erp_db < database/migration_v4.sql
mysql -u root erp_db < database/seed_v4.sql

# Konfigurasi
# Edit config/config.php sesuai kredensial MySQL Anda

# Jalankan
php -S localhost:8000
```

**Total:** 82 tabel, 36 modul, 10 jenis dokumen bernomor otomatis.
