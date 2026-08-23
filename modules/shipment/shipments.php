<?php
// -----------------------------------------------------
// Modul Shipment: Pengiriman & Tracking
// -----------------------------------------------------

$pageTitle = 'Shipment & Logistics';

function module_handle(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $doId = (int)$_POST['do_id'];
        $carrierId = (int)$_POST['carrier_id'];
        $trackingNo = trim($_POST['tracking_number'] ?? '');
        $shipDate = $_POST['ship_date'];
        $estArrival = $_POST['estimated_arrival'];

        Database::begin();
        try {
            $shNo = generateNumber('shipments', 'shipment_number', 'SHP');
            Database::query(
                'INSERT INTO shipments (shipment_number, do_id, carrier_id, tracking_number, ship_date, estimated_arrival, status, created_by) VALUES (?,?,?,?,?,?,\'PREPARING\',?)',
                [$shNo, $doId, $carrierId, $trackingNo, $shipDate, $estArrival, Auth::user()['id']]
            );
            Database::query("UPDATE delivery_orders SET status = 'SHIPPED' WHERE id = ?", [$doId]);
            Database::commit();
            logActivity('shipment', 'CREATE_SHIPMENT', "Shipment {$shNo} dibuat");
            setFlash('success', "Shipment {$shNo} dibuat.");
        } catch (Exception $ex) {
            Database::rollback();
            setFlash('danger', 'Gagal: ' . $ex->getMessage());
        }
        redirect('index.php?page=shipments');
    }

    if ($action === 'update_status') {
        $id = (int)$_POST['id'];
        $status = $_POST['status'];
        Database::query('UPDATE shipments SET status = ? WHERE id = ?', [$status, $id]);
        if ($status === 'DELIVERED') {
            Database::query('UPDATE shipments SET actual_arrival = CURDATE() WHERE id = ?', [$id]);
        }
        setFlash('success', 'Status shipment diupdate.');
        redirect('index.php?page=shipments');
    }
}

function module_render(): void
{
    $items = Database::all(
        'SELECT s.*, d.do_number, so.so_number, c.name AS customer_name, cr.name AS carrier_name
         FROM shipments s
         LEFT JOIN delivery_orders d ON d.id = s.do_id
         JOIN sales_orders so ON so.id = d.so_id
         JOIN customers c ON c.id = so.customer_id
         LEFT JOIN carriers cr ON cr.id = s.carrier_id
         ORDER BY s.ship_date DESC'
    );
    $carriers = Database::all('SELECT * FROM carriers ORDER BY name');
    $doWithoutShipment = Database::all(
        "SELECT d.id, d.do_number, so.so_number, c.name AS customer_name
         FROM delivery_orders d
         JOIN sales_orders so ON so.id = d.so_id
         JOIN customers c ON c.id = so.customer_id
         WHERE d.status = 'SHIPPED' AND d.id NOT IN (SELECT do_id FROM shipments)
         ORDER BY d.delivery_date DESC"
    );
    ?>
    <?php if (!empty($doWithoutShipment)): ?>
    <div class="card card-warning collapsed-card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-exclamation-triangle"></i> DO Belum Ada Shipment (<?= count($doWithoutShipment) ?>)</h3>
            <div class="card-tools"><button class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-plus"></i></button></div>
        </div>
        <div class="card-body p-0">
            <table class="table table-sm">
                <thead><tr><th>No. DO</th><th>No. SO</th><th>Customer</th><th width="100">Aksi</th></tr></thead>
                <tbody>
                <?php foreach ($doWithoutShipment as $do): ?>
                    <tr>
                        <td><?= e($do['do_number']) ?></td>
                        <td><?= e($do['so_number']) ?></td>
                        <td><?= e($do['customer_name']) ?></td>
                        <td>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="action" value="create">
                                <input type="hidden" name="do_id" value="<?= $do['id'] ?>">
                                <input type="hidden" name="ship_date" value="<?= date('Y-m-d') ?>">
                                <input type="hidden" name="estimated_arrival" value="<?= date('Y-m-d', strtotime('+3 days')) ?>">
                                <div class="form-inline">
                                    <select name="carrier_id" class="form-control form-control-sm" required>
                                        <option value="">- Carrier -</option>
                                        <?php foreach ($carriers as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
                                    </select>
                                    <input type="text" name="tracking_number" class="form-control form-control-sm" placeholder="No. Resi">
                                    <button class="btn btn-sm btn-primary"><i class="fas fa-plus"></i> Buat</button>
                                </div>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header"><h3 class="card-title">Daftar Shipment</h3></div>
        <div class="card-body">
            <table class="table table-bordered table-striped datatable">
                <thead><tr><th>No. Shipment</th><th>No. DO</th><th>Customer</th><th>Carrier</th><th>No. Resi</th><th>Tgl Kirim</th><th>ETA</th><th>Status</th><th width="80">Aksi</th></tr></thead>
                <tbody>
                <?php foreach ($items as $s): ?>
                    <tr>
                        <td><?= e($s['shipment_number']) ?></td>
                        <td><?= e($s['do_number'] ?? '-') ?></td>
                        <td><?= e($s['customer_name']) ?></td>
                        <td><?= e($s['carrier_name'] ?? '-') ?></td>
                        <td><?= e($s['tracking_number'] ?? '-') ?></td>
                        <td><?= fdate($s['ship_date']) ?></td>
                        <td><?= fdate($s['estimated_arrival']) ?></td>
                        <td><?= statusBadge($s['status']) ?></td>
                        <td>
                            <?php if (in_array($s['status'], ['PREPARING','SHIPPED','IN_TRANSIT'], true)): ?>
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                    <select name="status" class="form-control form-control-sm" onchange="if(confirm('Update status?')) this.form.submit()">
                                        <option value="PREPARING" <?= $s['status'] === 'PREPARING' ? 'selected' : '' ?>>Preparing</option>
                                        <option value="SHIPPED" <?= $s['status'] === 'SHIPPED' ? 'selected' : '' ?>>Shipped</option>
                                        <option value="IN_TRANSIT" <?= $s['status'] === 'IN_TRANSIT' ? 'selected' : '' ?>>In Transit</option>
                                        <option value="DELIVERED" <?= $s['status'] === 'DELIVERED' ? 'selected' : '' ?>>Delivered</option>
                                    </select>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3 class="card-title"><i class="fas fa-truck"></i> Daftar Carrier</h3></div>
        <div class="card-body p-0">
            <table class="table table-sm">
                <thead><tr><th>Kode</th><th>Nama</th><th>Tipe</th><th>Kontak</th><th>Telepon</th></tr></thead>
                <tbody>
                <?php foreach ($carriers as $c): ?>
                    <tr>
                        <td><?= e($c['code']) ?></td>
                        <td><?= e($c['name']) ?></td>
                        <td><?= statusBadge($c['type']) ?></td>
                        <td><?= e($c['contact'] ?? '-') ?></td>
                        <td><?= e($c['phone'] ?? '-') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}
