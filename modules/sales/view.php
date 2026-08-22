<?php
// -----------------------------------------------------
// Modul Sales: Detail Sales Order
// -----------------------------------------------------

function module_handle(): void
{
    $id = (int)($_GET['id'] ?? 0);
    $so = Database::row('SELECT so_number FROM sales_orders WHERE id = ?', [$id]);
    $GLOBALS['pageTitle'] = $so ? 'Detail ' . $so['so_number'] : 'Sales Order';
}

function module_render(): void
{
    $id = (int)($_GET['id'] ?? 0);
    $so = Database::row(
        'SELECT s.*, c.name AS customer_name, c.address AS customer_address, c.phone AS customer_phone, c.city,
                u.full_name AS creator
         FROM sales_orders s
         JOIN customers c ON c.id = s.customer_id
         LEFT JOIN users u ON u.id = s.created_by WHERE s.id = ?',
        [$id]
    );
    if (!$so) {
        setFlash('danger', 'Sales Order tidak ditemukan.');
        redirect('index.php?page=sales');
    }
    $items = Database::all(
        'SELECT i.*, p.code, p.name, p.unit, p.stock AS current_stock FROM sales_order_items i
         JOIN products p ON p.id = i.product_id WHERE i.so_id = ?',
        [$id]
    );
    ?>
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-file-alt"></i> <?= e($so['so_number']) ?> <?= statusBadge($so['status']) ?></h3>
                    <div class="card-tools">
                        <a href="index.php?page=sales" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <table class="table table-borderless table-sm">
                                <tr><td width="130" class="text-muted">Customer</td><td><b><?= e($so['customer_name']) ?></b></td></tr>
                                <tr><td class="text-muted">Alamat</td><td><?= e($so['customer_address']) ?>, <?= e($so['city']) ?></td></tr>
                                <tr><td class="text-muted">Telepon</td><td><?= e($so['customer_phone']) ?></td></tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless table-sm">
                                <tr><td width="130" class="text-muted">Tanggal Order</td><td><?= fdate($so['order_date']) ?></td></tr>
                                <tr><td class="text-muted">Dibuat Oleh</td><td><?= e($so['creator'] ?? '-') ?></td></tr>
                                <tr><td class="text-muted">Catatan</td><td><?= e($so['notes'] ?? '-') ?></td></tr>
                            </table>
                        </div>
                    </div>
                    <table class="table table-bordered">
                        <thead class="thead-light">
                            <tr><th>Kode</th><th>Produk</th><th>Satuan</th><th class="text-right">Stok</th><th class="text-right">Qty</th><th class="text-right">Harga</th><th class="text-right">Subtotal</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($items as $it): ?>
                            <tr>
                                <td><?= e($it['code']) ?></td>
                                <td><?= e($it['name']) ?></td>
                                <td><?= e($it['unit']) ?></td>
                                <td class="text-right">
                                    <span class="badge badge-<?= $it['current_stock'] >= $it['qty'] ? 'success' : 'danger' ?>">
                                        <?= number_format($it['current_stock']) ?>
                                    </span>
                                </td>
                                <td class="text-right"><?= number_format($it['qty']) ?></td>
                                <td class="text-right"><?= money($it['price']) ?></td>
                                <td class="text-right"><?= money($it['subtotal']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr><td colspan="6" class="text-right font-weight-bold">TOTAL</td>
                                <td class="text-right font-weight-bold text-info"><?= money($so['total']) ?></td></tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-outline card-info">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-cogs"></i> Workflow</h3></div>
                <div class="card-body">
                    <div class="mb-3">
                        <small class="text-muted">Alur: DRAFT &rarr; CONFIRMED &rarr; DELIVERED</small>
                        <div class="progress mt-2" style="height:8px">
                            <div class="progress-bar bg-info" style="width: <?= $so['status'] === 'DRAFT' ? '33%' : ($so['status'] === 'CONFIRMED' ? '66%' : '100%') ?>"></div>
                        </div>
                    </div>
                    <?php if ($so['status'] === 'DRAFT'): ?>
                        <form method="post" action="index.php?page=sales">
                            <input type="hidden" name="id" value="<?= $so['id'] ?>">
                            <button name="action" value="confirm" class="btn btn-info btn-block" onclick="return confirm('Konfirmasi SO ini?')">
                                <i class="fas fa-check"></i> Confirm Order
                            </button>
                            <button name="action" value="cancel" class="btn btn-danger btn-block" onclick="return confirm('Batalkan SO ini?')">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                        </form>
                    <?php elseif ($so['status'] === 'CONFIRMED'): ?>
                        <form method="post" action="index.php?page=sales">
                            <input type="hidden" name="id" value="<?= $so['id'] ?>">
                            <button name="action" value="deliver" class="btn btn-success btn-block" onclick="return confirm('Kirim barang? Stok akan berkurang dan jurnal penjualan dibuat.')">
                                <i class="fas fa-truck"></i> Kirim Barang (Delivery)
                            </button>
                            <button name="action" value="cancel" class="btn btn-danger btn-block" onclick="return confirm('Batalkan SO ini?')">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                        </form>
                    <?php elseif ($so['status'] === 'DELIVERED'): ?>
                        <div class="alert alert-success mb-0"><i class="fas fa-check-circle"></i> Barang sudah dikirim. Stok & jurnal sudah terupdate.</div>
                    <?php else: ?>
                        <div class="alert alert-danger mb-0"><i class="fas fa-ban"></i> SO telah dibatalkan.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php
}
