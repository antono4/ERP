<?php
// -----------------------------------------------------
// Modul Inventory: Stock Opname - Daftar
// -----------------------------------------------------

$pageTitle = 'Stock Opname';

function module_handle(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'confirm') {
        $opname = Database::row('SELECT * FROM stock_opnames WHERE id = ?', [$id]);
        if (!$opname || $opname['status'] !== 'OPEN') redirect('index.php?page=opname');

        $items = Database::all('SELECT * FROM stock_opname_items WHERE opname_id = ? AND physical_qty IS NOT NULL', [$id]);
        Database::begin();
        try {
            foreach ($items as $it) {
                $diff = $it['physical_qty'] - $it['system_qty'];
                if (abs($diff) < 0.001) continue;
                $type = $diff > 0 ? 'IN' : 'OUT';
                $qty = abs($diff);
                Database::query('UPDATE products SET stock = stock + ? WHERE id = ?', [$diff, $it['product_id']]);
                Database::query(
                    "INSERT INTO stock_movements (product_id, movement_type, qty, reference_type, reference_id, notes, created_by)
                     VALUES (?, ?, ?, 'ADJUSTMENT', ?, ?, ?)",
                    [$it['product_id'], $type, $qty, $id, "Stock opname {$opname['opname_number']}", Auth::user()['id']]
                );
            }
            Database::query("UPDATE stock_opnames SET status='CONFIRMED' WHERE id=?", [$id]);
            Database::commit();
            logActivity('inventory', 'OPNAME_CONFIRM', "Stock opname {$opname['opname_number']} dikonfirmasi");
            setFlash('success', "Opname {$opname['opname_number']} dikonfirmasi. Stok disesuaikan.");
        } catch (Exception $ex) {
            Database::rollback();
            setFlash('danger', 'Gagal: ' . $ex->getMessage());
        }
        redirect('index.php?page=opname');
    }

    if ($action === 'cancel') {
        Database::query("UPDATE stock_opnames SET status='CANCELLED' WHERE id=? AND status='OPEN'", [$id]);
        setFlash('warning', 'Opname dibatalkan.');
        redirect('index.php?page=opname');
    }
}

function module_render(): void
{
    $opnames = Database::all(
        'SELECT o.*, u.full_name AS creator,
            (SELECT COUNT(*) FROM stock_opname_items WHERE opname_id = o.id) AS item_count,
            (SELECT COUNT(*) FROM stock_opname_items WHERE opname_id = o.id AND physical_qty IS NOT NULL) AS counted_count
         FROM stock_opnames o
         LEFT JOIN users u ON u.id = o.created_by
         ORDER BY o.created_at DESC'
    );
    ?>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Daftar Stock Opname</h3>
            <div class="card-tools">
                <a href="index.php?page=opname_form" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Buat Opname</a>
            </div>
        </div>
        <div class="card-body">
            <table class="table table-bordered table-striped datatable">
                <thead><tr><th>No. Opname</th><th>Tanggal</th><th>Progress</th><th>Status</th><th>Oleh</th><th width="150">Aksi</th></tr></thead>
                <tbody>
                <?php foreach ($opnames as $o): ?>
                    <tr>
                        <td><a href="index.php?page=opname_view&id=<?= $o['id'] ?>"><?= e($o['opname_number']) ?></a></td>
                        <td><?= fdate($o['opname_date']) ?></td>
                        <td>
                            <?php $pct = $o['item_count'] > 0 ? round($o['counted_count'] / $o['item_count'] * 100) : 0; ?>
                            <div class="progress progress-sm">
                                <div class="progress-bar bg-primary" style="width: <?= $pct ?>%"></div>
                            </div>
                            <small><?= $o['counted_count'] ?>/<?= $o['item_count'] ?> item (<?= $pct ?>%)</small>
                        </td>
                        <td><?= statusBadge($o['status']) ?></td>
                        <td><?= e($o['creator'] ?? '-') ?></td>
                        <td>
                            <a href="index.php?page=opname_view&id=<?= $o['id'] ?>" class="btn btn-sm btn-info"><i class="fas fa-eye"></i></a>
                            <?php if ($o['status'] === 'OPEN'): ?>
                                <form method="post" class="d-inline" onsubmit="return confirm('Konfirmasi opname? Stok akan disesuaikan dengan hitungan fisik.')">
                                    <input type="hidden" name="id" value="<?= $o['id'] ?>">
                                    <button name="action" value="confirm" class="btn btn-sm btn-success"><i class="fas fa-check"></i> Konfirmasi</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}
