<?php
// -----------------------------------------------------
// Modul Purchasing: Detail Purchase Order
// -----------------------------------------------------

function module_handle(): void
{
    $id = (int)($_GET['id'] ?? 0);
    $po = Database::row('SELECT po_number FROM purchase_orders WHERE id = ?', [$id]);
    $GLOBALS['pageTitle'] = $po ? 'Detail ' . $po['po_number'] : 'Purchase Order';
}

function module_render(): void
{
    $id = (int)($_GET['id'] ?? 0);
    $po = Database::row(
        'SELECT p.*, s.name AS supplier_name, s.address AS supplier_address, s.phone AS supplier_phone,
                u.full_name AS creator
         FROM purchase_orders p
         JOIN suppliers s ON s.id = p.supplier_id
         LEFT JOIN users u ON u.id = p.created_by WHERE p.id = ?',
        [$id]
    );
    if (!$po) {
        setFlash('danger', 'Purchase Order tidak ditemukan.');
        redirect('index.php?page=purchase');
    }
    $items = Database::all(
        'SELECT i.*, p.code, p.name, p.unit FROM purchase_order_items i
         JOIN products p ON p.id = i.product_id WHERE i.po_id = ?',
        [$id]
    );
    ?>
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-file-alt"></i> <?= e($po['po_number']) ?> <?= statusBadge($po['status']) ?></h3>
                    <div class="card-tools">
                        <a href="index.php?page=purchase" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <table class="table table-borderless table-sm">
                                <tr><td width="130" class="text-muted">Supplier</td><td><b><?= e($po['supplier_name']) ?></b></td></tr>
                                <tr><td class="text-muted">Alamat</td><td><?= e($po['supplier_address']) ?></td></tr>
                                <tr><td class="text-muted">Telepon</td><td><?= e($po['supplier_phone']) ?></td></tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless table-sm">
                                <tr><td width="130" class="text-muted">Tanggal Order</td><td><?= fdate($po['order_date']) ?></td></tr>
                                <tr><td class="text-muted">Dibuat Oleh</td><td><?= e($po['creator'] ?? '-') ?></td></tr>
                                <tr><td class="text-muted">Catatan</td><td><?= e($po['notes'] ?? '-') ?></td></tr>
                            </table>
                        </div>
                    </div>
                    <table class="table table-bordered">
                        <thead class="thead-light">
                            <tr><th>Kode</th><th>Produk</th><th>Satuan</th><th class="text-right">Qty</th><th class="text-right">Harga</th><th class="text-right">Subtotal</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($items as $it): ?>
                            <tr>
                                <td><?= e($it['code']) ?></td>
                                <td><?= e($it['name']) ?></td>
                                <td><?= e($it['unit']) ?></td>
                                <td class="text-right"><?= number_format($it['qty']) ?></td>
                                <td class="text-right"><?= money($it['price']) ?></td>
                                <td class="text-right"><?= money($it['subtotal']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr><td colspan="5" class="text-right font-weight-bold">TOTAL</td>
                                <td class="text-right font-weight-bold text-primary"><?= money($po['total']) ?></td></tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-outline card-primary">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-cogs"></i> Workflow</h3></div>
                <div class="card-body">
                    <div class="mb-3">
                        <small class="text-muted">Alur: DRAFT &rarr; APPROVED &rarr; RECEIVED</small>
                        <div class="progress mt-2" style="height:8px">
                            <div class="progress-bar bg-success" style="width: <?= $po['status'] === 'DRAFT' ? '33%' : ($po['status'] === 'APPROVED' ? '66%' : '100%') ?>"></div>
                        </div>
                    </div>
                    <?php if ($po['status'] === 'DRAFT'): ?>
                        <form method="post" action="index.php?page=purchase">
                            <input type="hidden" name="id" value="<?= $po['id'] ?>">
                            <button name="action" value="approve" class="btn btn-warning btn-block" onclick="return confirm('Setujui PO ini?')">
                                <i class="fas fa-check"></i> Approve
                            </button>
                            <button name="action" value="cancel" class="btn btn-danger btn-block" onclick="return confirm('Batalkan PO ini?')">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                        </form>
                    <?php elseif ($po['status'] === 'APPROVED'): ?>
                        <form method="post" action="index.php?page=purchase">
                            <input type="hidden" name="id" value="<?= $po['id'] ?>">
                            <button name="action" value="receive" class="btn btn-success btn-block" onclick="return confirm('Terima barang? Stok akan bertambah dan jurnal dibuat.')">
                                <i class="fas fa-truck"></i> Terima Barang (Goods Receipt)
                            </button>
                            <button name="action" value="cancel" class="btn btn-danger btn-block" onclick="return confirm('Batalkan PO ini?')">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                        </form>
                    <?php elseif ($po['status'] === 'RECEIVED'): ?>
                        <div class="alert alert-success mb-0"><i class="fas fa-check-circle"></i> Barang sudah diterima. Stok & jurnal sudah terupdate.</div>
                    <?php else: ?>
                        <div class="alert alert-danger mb-0"><i class="fas fa-ban"></i> PO telah dibatalkan.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php
}
