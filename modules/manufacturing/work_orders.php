<?php
// -----------------------------------------------------
// Modul Manufacturing: Work Order (Perintah Produksi)
// -----------------------------------------------------

$pageTitle = 'Work Order (Produksi)';

function module_handle(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $productId = (int)$_POST['product_id'];
        $qty = (float)$_POST['qty_plan'];
        $date = $_POST['planned_date'];
        $notes = trim($_POST['notes'] ?? '');

        $bom = Database::all('SELECT * FROM bom_items WHERE product_id = ?', [$productId]);
        if (empty($bom)) {
            setFlash('danger', 'Produk ini belum punya BOM. Atur BOM dulu.');
            redirect('index.php?page=work_orders');
        }

        Database::begin();
        try {
            $woNo = generateNumber('work_orders', 'wo_number', 'WO');
            Database::query(
                'INSERT INTO work_orders (wo_number, product_id, qty_plan, planned_date, status, notes, created_by) VALUES (?,?,?,?,\'PLANNED\',?,?)',
                [$woNo, $productId, $qty, $date, $notes, Auth::user()['id']]
            );
            $woId = (int)Database::lastId();
            foreach ($bom as $b) {
                Database::query(
                    'INSERT INTO work_order_components (wo_id, product_id, qty_needed) VALUES (?,?,?)',
                    [$woId, $b['component_id'], $b['qty'] * $qty]
                );
            }
            Database::commit();
            logActivity('manufacturing', 'CREATE_WO', "Work Order {$woNo} dibuat");
            setFlash('success', "Work Order {$woNo} dibuat.");
            redirect('index.php?page=wo_view&id=' . $woId);
        } catch (Exception $ex) {
            Database::rollback();
            setFlash('danger', 'Gagal: ' . $ex->getMessage());
        }
        redirect('index.php?page=work_orders');
    }

    if ($action === 'start') {
        $id = (int)$_POST['id'];
        Database::query("UPDATE work_orders SET status='IN_PROGRESS' WHERE id=? AND status='PLANNED'", [$id]);
        setFlash('success', 'Produksi dimulai.');
        redirect('index.php?page=wo_view&id=' . $id);
    }

    if ($action === 'complete') {
        $id = (int)$_POST['id'];
        $wo = Database::row('SELECT * FROM work_orders WHERE id = ?', [$id]);
        if (!$wo || $wo['status'] !== 'IN_PROGRESS') redirect('index.php?page=work_orders');

        $components = Database::all(
            'SELECT c.*, p.stock, p.name FROM work_order_components c JOIN products p ON p.id = c.product_id WHERE c.wo_id = ?',
            [$id]
        );

        // Validasi stok bahan
        foreach ($components as $c) {
            if ($c['stock'] < $c['qty_needed']) {
                setFlash('danger', "Stok {$c['name']} tidak cukup (butuh {$c['qty_needed']}, sisa {$c['stock']}).");
                redirect('index.php?page=wo_view&id=' . $id);
            }
        }

        Database::begin();
        try {
            $totalCost = 0;
            // Keluarkan bahan baku
            foreach ($components as $c) {
                Database::query('UPDATE products SET stock = stock - ? WHERE id = ?', [$c['qty_needed'], $c['product_id']]);
                Database::query(
                    "INSERT INTO stock_movements (product_id, movement_type, qty, reference_type, reference_id, notes, created_by)
                     VALUES (?, 'OUT', ?, 'ADJUSTMENT', ?, ?, ?)",
                    [$c['product_id'], $c['qty_needed'], $id, "Material issue WO {$wo['wo_number']}", Auth::user()['id']]
                );
                Database::query('UPDATE work_order_components SET qty_issued = qty_needed WHERE id = ?', [$c['id']]);
                $price = Database::value('SELECT purchase_price FROM products WHERE id = ?', [$c['product_id']]);
                $totalCost += $c['qty_needed'] * $price;
            }
            // Masukkan barang jadi
            Database::query('UPDATE products SET stock = stock + ? WHERE id = ?', [$wo['qty_plan'], $wo['product_id']]);
            Database::query(
                "INSERT INTO stock_movements (product_id, movement_type, qty, reference_type, reference_id, notes, created_by)
                 VALUES (?, 'IN', ?, 'ADJUSTMENT', ?, ?, ?)",
                [$wo['product_id'], $wo['qty_plan'], $id, "Hasil produksi WO {$wo['wo_number']}", Auth::user()['id']]
            );
            Database::query("UPDATE work_orders SET status='COMPLETED', qty_done=? WHERE id=?", [$wo['qty_plan'], $id]);

            // Jurnal: Persediaan jadi (D) / Persediaan bahan (K) — simplifikasi
            $inv = Database::value("SELECT id FROM accounts WHERE code='1-1300'");
            if ($inv && $totalCost > 0) {
                $jeNo = generateNumber('journal_entries', 'entry_number', 'JE');
                Database::query(
                    'INSERT INTO journal_entries (entry_number, entry_date, description, reference, created_by) VALUES (?,?,?,?,?)',
                    [$jeNo, date('Y-m-d'), "Produksi {$wo['wo_number']}", $wo['wo_number'], Auth::user()['id']]
                );
                $jeId = (int)Database::lastId();
                Database::query('INSERT INTO journal_entry_lines (journal_id, account_id, debit, credit) VALUES (?,?,?,0)', [$jeId, $inv, $totalCost]);
                Database::query('INSERT INTO journal_entry_lines (journal_id, account_id, debit, credit) VALUES (?,?,0,?)', [$jeId, $inv, $totalCost]);
            }
            Database::commit();
            logActivity('manufacturing', 'COMPLETE_WO', "WO {$wo['wo_number']} selesai, biaya " . money($totalCost));
            setFlash('success', "WO {$wo['wo_number']} selesai. Stok barang jadi bertambah.");
        } catch (Exception $ex) {
            Database::rollback();
            setFlash('danger', 'Gagal: ' . $ex->getMessage());
        }
        redirect('index.php?page=wo_view&id=' . $id);
    }

    if ($action === 'cancel') {
        $id = (int)$_POST['id'];
        Database::query("UPDATE work_orders SET status='CANCELLED' WHERE id=? AND status IN ('PLANNED','IN_PROGRESS')", [$id]);
        setFlash('warning', 'Work Order dibatalkan.');
        redirect('index.php?page=work_orders');
    }
}

function module_render(): void
{
    $orders = Database::all(
        'SELECT w.*, p.code, p.name, u.full_name AS creator
         FROM work_orders w JOIN products p ON p.id = w.product_id
         LEFT JOIN users u ON u.id = w.created_by ORDER BY w.created_at DESC'
    );
    $products = Database::all(
        'SELECT DISTINCT p.id, p.code, p.name FROM products p JOIN bom_items b ON b.product_id = p.id ORDER BY p.name'
    );
    ?>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Daftar Work Order</h3>
            <div class="card-tools">
                <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#createWOModal"><i class="fas fa-plus"></i> Buat WO</button>
            </div>
        </div>
        <div class="card-body">
            <table class="table table-bordered table-striped datatable">
                <thead><tr><th>No. WO</th><th>Produk</th><th class="text-right">Qty Rencana</th><th class="text-right">Qty Selesai</th><th>Tanggal</th><th>Status</th><th>Oleh</th><th width="80">Aksi</th></tr></thead>
                <tbody>
                <?php foreach ($orders as $w): ?>
                    <tr>
                        <td><a href="index.php?page=wo_view&id=<?= $w['id'] ?>"><?= e($w['wo_number']) ?></a></td>
                        <td><?= e($w['code']) ?> - <?= e($w['name']) ?></td>
                        <td class="text-right"><?= number_format($w['qty_plan']) ?></td>
                        <td class="text-right"><?= number_format($w['qty_done']) ?></td>
                        <td><?= fdate($w['planned_date']) ?></td>
                        <td><?= statusBadge($w['status']) ?></td>
                        <td><?= e($w['creator'] ?? '-') ?></td>
                        <td><a href="index.php?page=wo_view&id=<?= $w['id'] ?>" class="btn btn-sm btn-info"><i class="fas fa-eye"></i></a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="createWOModal">
        <div class="modal-dialog">
            <form method="post" class="modal-content">
                <input type="hidden" name="action" value="create">
                <div class="modal-header"><h5 class="modal-title">Buat Work Order</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Produk Jadi (harus punya BOM)</label>
                        <select name="product_id" class="form-control select2" required>
                            <option value="">- Pilih -</option>
                            <?php foreach ($products as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= e($p['code']) ?> - <?= e($p['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>Qty Rencana Produksi</label><input type="number" name="qty_plan" class="form-control" min="1" step="any" value="1" required></div>
                    <div class="form-group"><label>Tanggal Rencana</label><input type="date" name="planned_date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
                    <div class="form-group"><label>Catatan</label><input type="text" name="notes" class="form-control"></div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary"><i class="fas fa-save"></i> Buat WO</button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                </div>
            </form>
        </div>
    </div>
    <?php
}
