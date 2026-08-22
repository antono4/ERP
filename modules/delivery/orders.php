<?php
// -----------------------------------------------------
// Modul Delivery: Daftar Surat Jalan
// -----------------------------------------------------

$pageTitle = 'Surat Jalan (Delivery Order)';

function module_handle(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $do = Database::row('SELECT * FROM delivery_orders WHERE id = ?', [$id]);
    if (!$do) redirect('index.php?page=delivery');

    if ($action === 'deliver' && $do['status'] === 'SHIPPED') {
        Database::query("UPDATE delivery_orders SET status='DELIVERED' WHERE id=?", [$id]);
        logActivity('delivery', 'DELIVERED', "Surat jalan {$do['do_number']} dikonfirmasi terkirim");
        setFlash('success', "Surat jalan {$do['do_number']} dikonfirmasi terkirim.");
    } elseif ($action === 'cancel' && $do['status'] === 'SHIPPED') {
        Database::query("UPDATE delivery_orders SET status='CANCELLED' WHERE id=?", [$id]);
        logActivity('delivery', 'CANCEL', "Surat jalan {$do['do_number']} dibatalkan");
        setFlash('warning', "Surat jalan {$do['do_number']} dibatalkan.");
    }
    redirect('index.php?page=delivery');
}

function module_render(): void
{
    $statusFilter = $_GET['status'] ?? '';
    $sql = "SELECT d.*, s.so_number, c.name AS customer_name, u.full_name AS creator
            FROM delivery_orders d
            JOIN sales_orders s ON s.id = d.so_id
            JOIN customers c ON c.id = s.customer_id
            LEFT JOIN users u ON u.id = d.created_by";
    $params = [];
    if ($statusFilter !== '') { $sql .= " WHERE d.status = ?"; $params[] = $statusFilter; }
    $sql .= " ORDER BY d.created_at DESC";
    $deliveries = Database::all($sql, $params);

    $soWithoutDO = Database::all(
        "SELECT s.id, s.so_number, s.order_date, c.name AS customer_name, s.total
         FROM sales_orders s JOIN customers c ON c.id = s.customer_id
         WHERE s.status = 'CONFIRMED'
         ORDER BY s.order_date DESC"
    );
    ?>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Daftar Surat Jalan</h3>
            <div class="card-tools">
                <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#createDOModal">
                    <i class="fas fa-plus"></i> Buat Surat Jalan
                </button>
            </div>
        </div>
        <div class="card-body">
            <div class="mb-3">
                <a href="index.php?page=delivery" class="btn btn-sm <?= $statusFilter === '' ? 'btn-dark' : 'btn-outline-dark' ?>">Semua</a>
                <?php foreach (['SHIPPED','DELIVERED','CANCELLED'] as $st): ?>
                    <a href="index.php?page=delivery&status=<?= $st ?>" class="btn btn-sm <?= $statusFilter === $st ? 'btn-dark' : 'btn-outline-dark' ?>"><?= $st ?></a>
                <?php endforeach; ?>
            </div>
            <table class="table table-bordered table-striped datatable">
                <thead>
                    <tr><th>No. DO</th><th>No. SO</th><th>Customer</th><th>Tanggal</th><th>Driver</th><th>Status</th><th>Oleh</th><th width="100">Aksi</th></tr>
                </thead>
                <tbody>
                <?php foreach ($deliveries as $d): ?>
                    <tr>
                        <td><?= e($d['do_number']) ?></td>
                        <td><a href="index.php?page=sales_view&id=<?= $d['so_id'] ?>"><?= e($d['so_number']) ?></a></td>
                        <td><?= e($d['customer_name']) ?></td>
                        <td><?= fdate($d['delivery_date']) ?></td>
                        <td><?= e($d['driver'] ?? '-') ?></td>
                        <td><?= statusBadge($d['status']) ?></td>
                        <td><?= e($d['creator'] ?? '-') ?></td>
                        <td>
                            <button class="btn btn-sm btn-info" data-toggle="modal" data-target="#detailModal<?= $d['id'] ?>"><i class="fas fa-eye"></i></button>
                            <?php if ($d['status'] === 'SHIPPED'): ?>
                                <form method="post" class="d-inline" onsubmit="return confirm('Konfirmasi terkirim?')">
                                    <input type="hidden" name="id" value="<?= $d['id'] ?>">
                                    <button name="action" value="deliver" class="btn btn-sm btn-success"><i class="fas fa-check"></i></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php foreach ($deliveries as $d):
        $items = Database::all(
            'SELECT di.*, p.code, p.name, p.unit FROM delivery_order_items di JOIN products p ON p.id = di.product_id WHERE di.do_id = ?',
            [$d['id']]
        );
    ?>
    <div class="modal fade" id="detailModal<?= $d['id'] ?>">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?= e($d['do_number']) ?> — <?= e($d['customer_name']) ?></h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <p>
                        <strong>Tanggal:</strong> <?= fdate($d['delivery_date']) ?> |
                        <strong>Driver:</strong> <?= e($d['driver'] ?? '-') ?> |
                        <strong>Kendaraan:</strong> <?= e($d['vehicle'] ?? '-') ?>
                    </p>
                    <table class="table table-sm table-bordered">
                        <thead class="thead-light"><tr><th>Kode</th><th>Produk</th><th>Satuan</th><th class="text-right">Qty</th></tr></thead>
                        <tbody>
                        <?php foreach ($items as $it): ?>
                            <tr><td><?= e($it['code']) ?></td><td><?= e($it['name']) ?></td><td><?= e($it['unit']) ?></td><td class="text-right"><?= number_format($it['qty']) ?></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <div class="modal fade" id="createDOModal">
        <div class="modal-dialog">
            <form method="post" action="index.php?page=delivery_form" class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Buat Surat Jalan</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button></div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Pilih Sales Order (status CONFIRMED)</label>
                        <select name="so_id" class="form-control select2" required>
                            <option value="">- Pilih SO -</option>
                            <?php foreach ($soWithoutDO as $so): ?>
                                <option value="<?= $so['id'] ?>"><?= e($so['so_number']) ?> - <?= e($so['customer_name']) ?> (<?= money($so['total']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Tanggal Kirim</label>
                        <input type="date" name="delivery_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group"><label>Driver</label><input type="text" name="driver" class="form-control" placeholder="Nama driver"></div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group"><label>Kendaraan</label><input type="text" name="vehicle" class="form-control" placeholder="No. plat"></div>
                        </div>
                    </div>
                    <div class="form-group"><label>Catatan</label><input type="text" name="notes" class="form-control"></div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary"><i class="fas fa-arrow-right"></i> Lanjutkan</button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                </div>
            </form>
        </div>
    </div>
    <?php
}
