<?php
// -----------------------------------------------------
// Modul WMS: Transfer Detail
// -----------------------------------------------------

function module_handle(): void
{
    $id = (int)($_GET['id'] ?? 0);
    $tr = Database::row('SELECT transfer_number FROM stock_transfers WHERE id = ?', [$id]);
    $GLOBALS['pageTitle'] = $tr ? 'Detail ' . $tr['transfer_number'] : 'Transfer Stok';
}

function module_render(): void
{
    $id = (int)($_GET['id'] ?? 0);
    $tr = Database::row(
        'SELECT t.*, fw.name AS from_name, tw.name AS to_name, u.full_name AS creator
         FROM stock_transfers t
         JOIN warehouses fw ON fw.id = t.from_warehouse
         JOIN warehouses tw ON tw.id = t.to_warehouse
         LEFT JOIN users u ON u.id = t.created_by WHERE t.id = ?',
        [$id]
    );
    if (!$tr) {
        setFlash('danger', 'Transfer tidak ditemukan.');
        redirect('index.php?page=transfers');
    }
    $GLOBALS['pageTitle'] = 'Detail ' . $tr['transfer_number'];
    $items = Database::all(
        'SELECT ti.*, p.code, p.name, p.unit, p.stock FROM stock_transfer_items ti JOIN products p ON p.id = ti.product_id WHERE ti.transfer_id = ?',
        [$id]
    );
    ?>
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-truck-moving"></i> <?= e($tr['transfer_number']) ?> <?= statusBadge($tr['status']) ?></h3>
                    <div class="card-tools"><a href="index.php?page=transfers" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a></div>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <table class="table table-borderless table-sm">
                                <tr><td width="130" class="text-muted">Dari Gudang</td><td><b><?= e($tr['from_name']) ?></b></td></tr>
                                <tr><td class="text-muted">Ke Gudang</td><td><b><?= e($tr['to_name']) ?></b></td></tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless table-sm">
                                <tr><td width="130" class="text-muted">Tanggal</td><td><?= fdate($tr['transfer_date']) ?></td></tr>
                                <tr><td class="text-muted">Dibuat Oleh</td><td><?= e($tr['creator'] ?? '-') ?></td></tr>
                                <tr><td class="text-muted">Catatan</td><td><?= e($tr['notes'] ?? '-') ?></td></tr>
                            </table>
                        </div>
                    </div>
                    <table class="table table-bordered">
                        <thead class="thead-light"><tr><th>Kode</th><th>Produk</th><th>Satuan</th><th class="text-right">Qty</th><th class="text-right">Stok Asal</th></tr></thead>
                        <tbody>
                        <?php foreach ($items as $it): ?>
                            <tr>
                                <td><?= e($it['code']) ?></td>
                                <td><?= e($it['name']) ?></td>
                                <td><?= e($it['unit']) ?></td>
                                <td class="text-right"><?= number_format($it['qty']) ?></td>
                                <td class="text-right"><span class="badge badge-<?= $it['stock'] >= $it['qty'] ? 'success' : 'danger' ?>"><?= number_format($it['stock']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-outline card-primary">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-cogs"></i> Workflow</h3></div>
                <div class="card-body">
                    <?php if ($tr['status'] === 'DRAFT'): ?>
                        <form method="post" action="index.php?page=transfers">
                            <input type="hidden" name="id" value="<?= $tr['id'] ?>">
                            <button name="action" value="confirm" class="btn btn-success btn-block" onclick="return confirm('Konfirmasi transfer? Stok akan dipindahkan.')"><i class="fas fa-check"></i> Konfirmasi Transfer</button>
                            <button name="action" value="cancel" class="btn btn-danger btn-block" onclick="return confirm('Batalkan transfer?')"><i class="fas fa-times"></i> Batal</button>
                        </form>
                    <?php elseif ($tr['status'] === 'CONFIRMED'): ?>
                        <div class="alert alert-success mb-0"><i class="fas fa-check-circle"></i> Transfer sudah dikonfirmasi. Stok sudah dipindahkan.</div>
                    <?php else: ?>
                        <div class="alert alert-danger mb-0"><i class="fas fa-ban"></i> Transfer dibatalkan.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php
}
