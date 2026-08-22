<?php
// -----------------------------------------------------
// Modul WMS: Transfer Stok Antar Gudang
// -----------------------------------------------------

$pageTitle = 'Transfer Stok';

function module_handle(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $action = $_POST['action'] ?? '';

    if ($action === 'confirm') {
        $id = (int)$_POST['id'];
        $tr = Database::row('SELECT * FROM stock_transfers WHERE id = ?', [$id]);
        if (!$tr || $tr['status'] !== 'DRAFT') redirect('index.php?page=transfers');

        $items = Database::all('SELECT t.*, p.stock FROM stock_transfer_items t JOIN products p ON p.id = t.product_id WHERE t.transfer_id = ?', [$id]);
        foreach ($items as $it) {
            if ($it['stock'] < $it['qty']) {
                $pName = Database::value('SELECT name FROM products WHERE id = ?', [$it['product_id']]);
                setFlash('danger', "Stok {$pName} tidak cukup (sisa {$it['stock']}).");
                redirect('index.php?page=transfer_view&id=' . $id);
            }
        }

        Database::begin();
        try {
            foreach ($items as $it) {
                Database::query('UPDATE products SET stock = stock - ? WHERE id = ?', [$it['qty'], $it['product_id']]);
                Database::query(
                    "INSERT INTO stock_movements (product_id, movement_type, qty, reference_type, reference_id, notes, created_by)
                     VALUES (?, 'OUT', ?, 'ADJUSTMENT', ?, ?, ?)",
                    [$it['product_id'], $it['qty'], $id, "Transfer keluar {$tr['transfer_number']}", Auth::user()['id']]
                );
                Database::query('UPDATE products SET stock = stock + ? WHERE id = ?', [$it['qty'], $it['product_id']]);
                Database::query(
                    "INSERT INTO stock_movements (product_id, movement_type, qty, reference_type, reference_id, notes, created_by)
                     VALUES (?, 'IN', ?, 'ADJUSTMENT', ?, ?, ?)",
                    [$it['product_id'], $it['qty'], $id, "Transfer masuk {$tr['transfer_number']}", Auth::user()['id']]
                );
                // per-warehouse stock
                Database::query(
                    'INSERT INTO warehouse_stocks (warehouse_id, product_id, qty) VALUES (?,?,?)
                     ON DUPLICATE KEY UPDATE qty = qty - VALUES(qty)',
                    [$tr['from_warehouse'], $it['product_id'], $it['qty']]
                );
                Database::query(
                    'INSERT INTO warehouse_stocks (warehouse_id, product_id, qty) VALUES (?,?,?)
                     ON DUPLICATE KEY UPDATE qty = qty + VALUES(qty)',
                    [$tr['to_warehouse'], $it['product_id'], $it['qty']]
                );
            }
            Database::query("UPDATE stock_transfers SET status='CONFIRMED' WHERE id=?", [$id]);
            Database::commit();
            logActivity('wms', 'CONFIRM_TRANSFER', "Transfer {$tr['transfer_number']} dikonfirmasi");
            setFlash('success', "Transfer {$tr['transfer_number']} dikonfirmasi.");
        } catch (Exception $ex) {
            Database::rollback();
            setFlash('danger', 'Gagal: ' . $ex->getMessage());
        }
        redirect('index.php?page=transfer_view&id=' . $id);
    }

    if ($action === 'cancel') {
        $id = (int)$_POST['id'];
        Database::query("UPDATE stock_transfers SET status='CANCELLED' WHERE id=? AND status='DRAFT'", [$id]);
        setFlash('warning', 'Transfer dibatalkan.');
        redirect('index.php?page=transfers');
    }
}

function module_render(): void
{
    $items = Database::all(
        'SELECT t.*, fw.name AS from_name, tw.name AS to_name, u.full_name AS creator,
            (SELECT COUNT(*) FROM stock_transfer_items WHERE transfer_id = t.id) AS item_count
         FROM stock_transfers t
         JOIN warehouses fw ON fw.id = t.from_warehouse
         JOIN warehouses tw ON tw.id = t.to_warehouse
         LEFT JOIN users u ON u.id = t.created_by ORDER BY t.created_at DESC'
    );
    ?>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Daftar Transfer Stok</h3>
            <div class="card-tools">
                <a href="index.php?page=transfer_form" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Buat Transfer</a>
            </div>
        </div>
        <div class="card-body">
            <table class="table table-bordered table-striped datatable">
                <thead><tr><th>No. Transfer</th><th>Dari</th><th>Ke</th><th>Tanggal</th><th class="text-right">Item</th><th>Status</th><th>Oleh</th><th width="80">Aksi</th></tr></thead>
                <tbody>
                <?php foreach ($items as $t): ?>
                    <tr>
                        <td><a href="index.php?page=transfer_view&id=<?= $t['id'] ?>"><?= e($t['transfer_number']) ?></a></td>
                        <td><?= e($t['from_name']) ?></td>
                        <td><?= e($t['to_name']) ?></td>
                        <td><?= fdate($t['transfer_date']) ?></td>
                        <td class="text-right"><span class="badge badge-info"><?= $t['item_count'] ?></span></td>
                        <td><?= statusBadge($t['status']) ?></td>
                        <td><?= e($t['creator'] ?? '-') ?></td>
                        <td><a href="index.php?page=transfer_view&id=<?= $t['id'] ?>" class="btn btn-sm btn-info"><i class="fas fa-eye"></i></a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}
