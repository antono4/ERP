<?php
// -----------------------------------------------------
// Modul Manufacturing: Work Order Detail
// -----------------------------------------------------

function module_handle(): void
{
    $id = (int)($_GET['id'] ?? 0);
    $wo = Database::row('SELECT wo_number FROM work_orders WHERE id = ?', [$id]);
    $GLOBALS['pageTitle'] = $wo ? 'Detail ' . $wo['wo_number'] : 'Work Order';
}

function module_render(): void
{
    $id = (int)($_GET['id'] ?? 0);
    $wo = Database::row(
        'SELECT w.*, p.code, p.name, p.unit, u.full_name AS creator
         FROM work_orders w JOIN products p ON p.id = w.product_id
         LEFT JOIN users u ON u.id = w.created_by WHERE w.id = ?',
        [$id]
    );
    if (!$wo) {
        setFlash('danger', 'Work Order tidak ditemukan.');
        redirect('index.php?page=work_orders');
    }
    $GLOBALS['pageTitle'] = 'Detail ' . $wo['wo_number'];
    $components = Database::all(
        'SELECT c.*, p.code, p.name, p.unit, p.stock, p.purchase_price
         FROM work_order_components c JOIN products p ON p.id = c.product_id WHERE c.wo_id = ?',
        [$id]
    );
    $totalCost = 0;
    foreach ($components as $c) { $totalCost += $c['qty_needed'] * $c['purchase_price']; }
    ?>
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-industry"></i> <?= e($wo['wo_number']) ?> <?= statusBadge($wo['status']) ?></h3>
                    <div class="card-tools"><a href="index.php?page=work_orders" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a></div>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <table class="table table-borderless table-sm">
                                <tr><td width="140" class="text-muted">Produk Jadi</td><td><b><?= e($wo['code']) ?> - <?= e($wo['name']) ?></b></td></tr>
                                <tr><td class="text-muted">Qty Rencana</td><td><?= number_format($wo['qty_plan']) ?> <?= e($wo['unit']) ?></td></tr>
                                <tr><td class="text-muted">Qty Selesai</td><td><?= number_format($wo['qty_done']) ?> <?= e($wo['unit']) ?></td></tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless table-sm">
                                <tr><td width="140" class="text-muted">Tanggal Rencana</td><td><?= fdate($wo['planned_date']) ?></td></tr>
                                <tr><td class="text-muted">Dibuat Oleh</td><td><?= e($wo['creator'] ?? '-') ?></td></tr>
                                <tr><td class="text-muted">Catatan</td><td><?= e($wo['notes'] ?? '-') ?></td></tr>
                            </table>
                        </div>
                    </div>
                    <h5><i class="fas fa-cogs"></i> Kebutuhan Bahan (BOM)</h5>
                    <table class="table table-bordered">
                        <thead class="thead-light">
                            <tr><th>Kode</th><th>Bahan</th><th class="text-right">Dibutuhkan</th><th class="text-right">Dikeluarkan</th><th class="text-right">Stok</th><th class="text-right">Est. Biaya</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($components as $c): ?>
                            <tr>
                                <td><?= e($c['code']) ?></td>
                                <td><?= e($c['name']) ?></td>
                                <td class="text-right"><?= number_format($c['qty_needed']) ?></td>
                                <td class="text-right"><?= number_format($c['qty_issued']) ?></td>
                                <td class="text-right">
                                    <span class="badge badge-<?= $c['stock'] >= $c['qty_needed'] ? 'success' : 'danger' ?>"><?= number_format($c['stock']) ?></span>
                                </td>
                                <td class="text-right"><?= money($c['qty_needed'] * $c['purchase_price']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot><tr><td colspan="5" class="text-right font-weight-bold">TOTAL ESTIMASI BIAYA</td><td class="text-right font-weight-bold text-primary"><?= money($totalCost) ?></td></tr></tfoot>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-outline card-primary">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-cogs"></i> Workflow</h3></div>
                <div class="card-body">
                    <small class="text-muted">Alur: PLANNED → IN_PROGRESS → COMPLETED</small>
                    <div class="progress mt-2 mb-3" style="height:8px">
                        <div class="progress-bar bg-primary" style="width: <?= $wo['status'] === 'PLANNED' ? '33%' : ($wo['status'] === 'IN_PROGRESS' ? '66%' : '100%') ?>"></div>
                    </div>
                    <?php if ($wo['status'] === 'PLANNED'): ?>
                        <form method="post" action="index.php?page=work_orders">
                            <input type="hidden" name="id" value="<?= $wo['id'] ?>">
                            <button name="action" value="start" class="btn btn-primary btn-block" onclick="return confirm('Mulai produksi?')"><i class="fas fa-play"></i> Mulai Produksi</button>
                            <button name="action" value="cancel" class="btn btn-danger btn-block" onclick="return confirm('Batalkan WO?')"><i class="fas fa-times"></i> Batal</button>
                        </form>
                    <?php elseif ($wo['status'] === 'IN_PROGRESS'): ?>
                        <form method="post" action="index.php?page=work_orders">
                            <input type="hidden" name="id" value="<?= $wo['id'] ?>">
                            <button name="action" value="complete" class="btn btn-success btn-block" onclick="return confirm('Selesaikan produksi? Bahan akan dikeluarkan & barang jadi masuk stok.')"><i class="fas fa-check"></i> Selesaikan Produksi</button>
                            <button name="action" value="cancel" class="btn btn-danger btn-block" onclick="return confirm('Batalkan WO?')"><i class="fas fa-times"></i> Batal</button>
                        </form>
                    <?php elseif ($wo['status'] === 'COMPLETED'): ?>
                        <div class="alert alert-success mb-0"><i class="fas fa-check-circle"></i> Produksi selesai. Barang jadi sudah masuk stok.</div>
                    <?php else: ?>
                        <div class="alert alert-danger mb-0"><i class="fas fa-ban"></i> WO dibatalkan.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php
}
