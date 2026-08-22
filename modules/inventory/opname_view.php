<?php
// -----------------------------------------------------
// Modul Inventory: Stock Opname - Detail & Input Fisik
// -----------------------------------------------------

function module_handle(): void
{
    $id = (int)($_GET['id'] ?? 0);
    $opname = Database::row('SELECT opname_number FROM stock_opnames WHERE id = ?', [$id]);
    $GLOBALS['pageTitle'] = $opname ? 'Detail ' . $opname['opname_number'] : 'Stock Opname';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        if ($action === 'save_count' && $opname) {
            $physicalQtys = $_POST['physical_qty'] ?? [];
            foreach ($physicalQtys as $itemId => $qty) {
                $qty = $qty === '' ? null : (float)$qty;
                Database::query('UPDATE stock_opname_items SET physical_qty = ? WHERE id = ? AND opname_id = ?', [$qty, (int)$itemId, $id]);
            }
            logActivity('inventory', 'OPNAME_COUNT', "Hitung fisik {$opname['opname_number']} diupdate");
            setFlash('success', 'Hasil hitung fisik disimpan.');
            redirect('index.php?page=opname_view&id=' . $id);
        }
    }
}

function module_render(): void
{
    $id = (int)($_GET['id'] ?? 0);
    $opname = Database::row(
        'SELECT o.*, u.full_name AS creator FROM stock_opnames o LEFT JOIN users u ON u.id = o.created_by WHERE o.id = ?',
        [$id]
    );
    if (!$opname) {
        setFlash('danger', 'Opname tidak ditemukan.');
        redirect('index.php?page=opname');
    }
    $GLOBALS['pageTitle'] = 'Detail ' . $opname['opname_number'];

    $items = Database::all(
        'SELECT oi.*, p.code, p.name, p.unit, c.name AS category_name
         FROM stock_opname_items oi
         JOIN products p ON p.id = oi.product_id
         LEFT JOIN categories c ON c.id = p.category_id
         WHERE oi.opname_id = ? ORDER BY p.code',
        [$id]
    );
    $totalDiff = 0;
    $diffCount = 0;
    foreach ($items as $it) {
        if ($it['physical_qty'] !== null) {
            $diff = $it['physical_qty'] - $it['system_qty'];
            $totalDiff += $diff;
            if (abs($diff) > 0.001) $diffCount++;
        }
    }
    ?>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-clipboard-check"></i> <?= e($opname['opname_number']) ?> <?= statusBadge($opname['status']) ?>
            </h3>
            <div class="card-tools">
                <a href="index.php?page=opname" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
            </div>
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-4">
                    <table class="table table-borderless table-sm">
                        <tr><td width="130" class="text-muted">Tanggal</td><td><?= fdate($opname['opname_date']) ?></td></tr>
                        <tr><td class="text-muted">Dibuat Oleh</td><td><?= e($opname['creator'] ?? '-') ?></td></tr>
                        <tr><td class="text-muted">Catatan</td><td><?= e($opname['notes'] ?? '-') ?></td></tr>
                    </table>
                </div>
                <div class="col-md-8">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="info-box bg-info"><span class="info-box-icon"><i class="fas fa-boxes"></i></span>
                                <div class="info-box-content"><span class="info-box-text">Total Item</span><span class="info-box-number"><?= count($items) ?></span></div></div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-box bg-warning"><span class="info-box-icon"><i class="fas fa-exclamation-triangle"></i></span>
                                <div class="info-box-content"><span class="info-box-text">Selisih</span><span class="info-box-number"><?= $diffCount ?> item</span></div></div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-box bg-<?= $totalDiff >= 0 ? 'success' : 'danger' ?>"><span class="info-box-icon"><i class="fas fa-balance-scale"></i></span>
                                <div class="info-box-content"><span class="info-box-text">Total Selisih</span><span class="info-box-number"><?= number_format($totalDiff) ?></span></div></div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($opname['status'] === 'OPEN'): ?>
            <form method="post">
                <input type="hidden" name="action" value="save_count">
            <?php endif; ?>
            <table class="table table-bordered table-striped">
                <thead class="thead-light">
                    <tr>
                        <th>Kode</th><th>Produk</th><th>Kategori</th><th>Satuan</th>
                        <th class="text-right">Stok Sistem</th>
                        <th width="150">Hitung Fisik</th>
                        <th class="text-right">Selisih</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($items as $it): ?>
                    <?php
                    $diff = $it['physical_qty'] !== null ? $it['physical_qty'] - $it['system_qty'] : null;
                    $diffClass = $diff === null ? '' : (abs($diff) < 0.001 ? 'text-success' : ($diff > 0 ? 'text-primary' : 'text-danger'));
                    ?>
                    <tr>
                        <td><?= e($it['code']) ?></td>
                        <td><?= e($it['name']) ?></td>
                        <td><?= e($it['category_name'] ?? '-') ?></td>
                        <td><?= e($it['unit']) ?></td>
                        <td class="text-right"><?= number_format($it['system_qty']) ?></td>
                        <td>
                            <?php if ($opname['status'] === 'OPEN'): ?>
                                <input type="number" name="physical_qty[<?= $it['id'] ?>]" class="form-control form-control-sm text-right"
                                       step="any" min="0" value="<?= $it['physical_qty'] ?? '' ?>" placeholder="Belum dihitung">
                            <?php else: ?>
                                <?= $it['physical_qty'] !== null ? number_format($it['physical_qty']) : '-' ?>
                            <?php endif; ?>
                        </td>
                        <td class="text-right font-weight-bold <?= $diffClass ?>">
                            <?= $diff !== null ? number_format($diff) : '-' ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php if ($opname['status'] === 'OPEN'): ?>
                <div class="text-right mt-3">
                    <button class="btn btn-primary"><i class="fas fa-save"></i> Simpan Hitungan</button>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <?php
}
