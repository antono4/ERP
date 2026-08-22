<?php
// -----------------------------------------------------
// Modul Purchasing: Daftar Purchase Order
// -----------------------------------------------------

$pageTitle = 'Purchase Order';

function module_handle(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $po = Database::row('SELECT * FROM purchase_orders WHERE id = ?', [$id]);
    if (!$po) redirect('index.php?page=purchase');

    if ($action === 'approve' && $po['status'] === 'DRAFT') {
        Database::query("UPDATE purchase_orders SET status='APPROVED' WHERE id=?", [$id]);
        setFlash('success', "PO {$po['po_number']} disetujui.");
    } elseif ($action === 'cancel' && in_array($po['status'], ['DRAFT','APPROVED'], true)) {
        Database::query("UPDATE purchase_orders SET status='CANCELLED' WHERE id=?", [$id]);
        setFlash('warning', "PO {$po['po_number']} dibatalkan.");
    } elseif ($action === 'receive' && $po['status'] === 'APPROVED') {
        // Penerimaan barang: stok masuk + kartu stok + jurnal
        $items = Database::all('SELECT * FROM purchase_order_items WHERE po_id = ?', [$id]);
        Database::begin();
        try {
            foreach ($items as $it) {
                Database::query('UPDATE products SET stock = stock + ? WHERE id = ?', [$it['qty'], $it['product_id']]);
                Database::query(
                    "INSERT INTO stock_movements (product_id, movement_type, qty, reference_type, reference_id, notes, created_by)
                     VALUES (?, 'IN', ?, 'PURCHASE', ?, ?, ?)",
                    [$it['product_id'], $it['qty'], $id, "Penerimaan {$po['po_number']}", Auth::user()['id']]
                );
            }
            Database::query("UPDATE purchase_orders SET status='RECEIVED' WHERE id=?", [$id]);

            // Auto jurnal: Persediaan (D) / Hutang Usaha (K)
            $inv = Database::value("SELECT id FROM accounts WHERE code='1-1300'");
            $ap  = Database::value("SELECT id FROM accounts WHERE code='2-1000'");
            if ($inv && $ap && $po['total'] > 0) {
                $jeNo = generateNumber('journal_entries', 'entry_number', 'JE');
                Database::query(
                    'INSERT INTO journal_entries (entry_number, entry_date, description, reference, created_by) VALUES (?,?,?,?,?)',
                    [$jeNo, date('Y-m-d'), "Pembelian {$po['po_number']}", $po['po_number'], Auth::user()['id']]
                );
                $jeId = (int)Database::lastId();
                Database::query('INSERT INTO journal_entry_lines (journal_id, account_id, debit, credit) VALUES (?,?,?,0)', [$jeId, $inv, $po['total']]);
                Database::query('INSERT INTO journal_entry_lines (journal_id, account_id, debit, credit) VALUES (?,?,0,?)', [$jeId, $ap, $po['total']]);
            }
            Database::commit();
            setFlash('success', "Barang PO {$po['po_number']} diterima. Stok & jurnal terupdate.");
        } catch (Exception $ex) {
            Database::rollback();
            setFlash('danger', 'Gagal menerima barang: ' . $ex->getMessage());
        }
    }
    redirect('index.php?page=purchase');
}

function module_render(): void
{
    $statusFilter = $_GET['status'] ?? '';
    $sql = "SELECT p.*, s.name AS supplier_name, u.full_name AS creator
            FROM purchase_orders p
            JOIN suppliers s ON s.id = p.supplier_id
            LEFT JOIN users u ON u.id = p.created_by";
    $params = [];
    if ($statusFilter !== '') {
        $sql .= " WHERE p.status = ?";
        $params[] = $statusFilter;
    }
    $sql .= " ORDER BY p.created_at DESC";
    $orders = Database::all($sql, $params);
    ?>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Daftar Purchase Order</h3>
            <div class="card-tools">
                <a href="index.php?page=purchase_form" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Buat PO Baru</a>
            </div>
        </div>
        <div class="card-body">
            <div class="mb-3">
                <a href="index.php?page=purchase" class="btn btn-sm <?= $statusFilter === '' ? 'btn-dark' : 'btn-outline-dark' ?>">Semua</a>
                <?php foreach (['DRAFT','APPROVED','RECEIVED','CANCELLED'] as $st): ?>
                    <a href="index.php?page=purchase&status=<?= $st ?>" class="btn btn-sm <?= $statusFilter === $st ? 'btn-dark' : 'btn-outline-dark' ?>"><?= $st ?></a>
                <?php endforeach; ?>
            </div>
            <table class="table table-bordered table-striped datatable">
                <thead>
                    <tr>
                        <th>No. PO</th><th>Tanggal</th><th>Supplier</th>
                        <th class="text-right">Total</th><th>Status</th><th>Dibuat Oleh</th><th width="80">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($orders as $o): ?>
                    <tr>
                        <td><a href="index.php?page=purchase_view&id=<?= $o['id'] ?>"><?= e($o['po_number']) ?></a></td>
                        <td><?= fdate($o['order_date']) ?></td>
                        <td><?= e($o['supplier_name']) ?></td>
                        <td class="text-right"><?= money($o['total']) ?></td>
                        <td><?= statusBadge($o['status']) ?></td>
                        <td><?= e($o['creator'] ?? '-') ?></td>
                        <td><a href="index.php?page=purchase_view&id=<?= $o['id'] ?>" class="btn btn-sm btn-info"><i class="fas fa-eye"></i></a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}
