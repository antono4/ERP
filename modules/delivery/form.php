<?php
// -----------------------------------------------------
// Modul Delivery: Form Buat Surat Jalan (pilih item & qty)
// -----------------------------------------------------

$pageTitle = 'Buat Surat Jalan';

function module_handle(): void
{
    $id = (int)($_GET['so_id'] ?? 0);
    $so = Database::row('SELECT so_number FROM sales_orders WHERE id = ?', [$id]);
    $GLOBALS['pageTitle'] = $so ? 'Buat Surat Jalan — ' . $so['so_number'] : 'Buat Surat Jalan';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['so_id'])) {
        return; // GET: tampilkan form, jangan redirect
    }

    $soId = (int)$_POST['so_id'];
    $deliveryDate = $_POST['delivery_date'];
    $driver = trim($_POST['driver'] ?? '');
    $vehicle = trim($_POST['vehicle'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $qtys = $_POST['qty'] ?? [];

    $so = Database::row('SELECT s.*, c.name AS customer_name FROM sales_orders s JOIN customers c ON c.id = s.customer_id WHERE s.id = ?', [$soId]);
    if (!$so || $so['status'] !== 'CONFIRMED') {
        setFlash('danger', 'Sales Order tidak valid atau sudah diproses.');
        redirect('index.php?page=delivery');
    }

    // Ambil item SO yang belum fully delivered
    $soItems = Database::all(
        'SELECT i.*, p.name, p.code, p.unit, p.stock
         FROM sales_order_items i JOIN products p ON p.id = i.product_id WHERE i.so_id = ?',
        [$soId]
    );

    $validItems = [];
    foreach ($soItems as $item) {
        $qty = (float)($qtys[$item['id']] ?? 0);
        if ($qty > 0) {
            $delivered = (float)Database::value(
                "SELECT COALESCE(SUM(di.qty),0) FROM delivery_order_items di
                 JOIN delivery_orders d ON d.id = di.do_id
                 WHERE d.so_id = ? AND di.product_id = ? AND d.status != 'CANCELLED'",
                [$soId, $item['product_id']]
            );
            $remaining = $item['qty'] - $delivered;
            if ($qty > $remaining) {
                setFlash('danger', "Qty {$item['name']} melebihi sisa yang harus dikirim ({$remaining}).");
                redirect('index.php?page=delivery_form&so_id=' . $soId);
            }
            if ($qty > $item['stock']) {
                setFlash('danger', "Stok {$item['name']} tidak cukup (sisa {$item['stock']}).");
                redirect('index.php?page=delivery_form&so_id=' . $soId);
            }
            $validItems[] = ['product_id' => $item['product_id'], 'qty' => $qty, 'price' => $item['price'], 'so_item_id' => $item['id']];
        }
    }

    if (empty($validItems)) {
        setFlash('danger', 'Minimal satu item harus diisi qty-nya.');
        redirect('index.php?page=delivery_form&so_id=' . $soId);
    }

    Database::begin();
    try {
        $doNumber = generateNumber('delivery_orders', 'do_number', 'DO');
        Database::query(
            'INSERT INTO delivery_orders (do_number, so_id, delivery_date, driver, vehicle, status, notes, created_by) VALUES (?,?,?,?,?,\'SHIPPED\',?,?)',
            [$doNumber, $soId, $deliveryDate, $driver, $vehicle, $notes, Auth::user()['id']]
        );
        $doId = (int)Database::lastId();

        foreach ($validItems as $it) {
            Database::query(
                'INSERT INTO delivery_order_items (do_id, product_id, qty) VALUES (?,?,?)',
                [$doId, $it['product_id'], $it['qty']]
            );
            // Kurangi stok
            Database::query('UPDATE products SET stock = stock - ? WHERE id = ?', [$it['qty'], $it['product_id']]);
            // Kartu stok
            Database::query(
                "INSERT INTO stock_movements (product_id, movement_type, qty, reference_type, reference_id, notes, created_by)
                 VALUES (?, 'OUT', ?, 'SALES', ?, ?, ?)",
                [$it['product_id'], $it['qty'], $doId, "Pengiriman {$doNumber} (SO {$so['so_number']})", Auth::user()['id']]
            );
        }

        // Cek apakah semua item SO sudah terkirim semua
        $allDelivered = true;
        foreach ($soItems as $item) {
            $delivered = (float)Database::value(
                "SELECT COALESCE(SUM(di.qty),0) FROM delivery_order_items di
                 JOIN delivery_orders d ON d.id = di.do_id
                 WHERE d.so_id = ? AND di.product_id = ? AND d.status != 'CANCELLED'",
                [$soId, $item['product_id']]
            );
            if ($delivered < $item['qty']) { $allDelivered = false; break; }
        }

        if ($allDelivered) {
            Database::query("UPDATE sales_orders SET status='DELIVERED' WHERE id=?", [$soId]);
        }

        Database::commit();
        logActivity('delivery', 'CREATE_DO', "Surat jalan {$doNumber} untuk SO {$so['so_number']}");
        setFlash('success', "Surat jalan {$doNumber} berhasil dibuat.");
        redirect('index.php?page=delivery');
    } catch (Exception $ex) {
        Database::rollback();
        setFlash('danger', 'Gagal membuat surat jalan: ' . $ex->getMessage());
        redirect('index.php?page=delivery_form&so_id=' . $soId);
    }
}

function module_render(): void
{
    $soId = (int)($_GET['so_id'] ?? 0);
    $so = Database::row(
        'SELECT s.*, c.name AS customer_name, c.address FROM sales_orders s JOIN customers c ON c.id = s.customer_id WHERE s.id = ?',
        [$soId]
    );
    if (!$so || $so['status'] !== 'CONFIRMED') {
        setFlash('danger', 'Sales Order tidak valid.');
        redirect('index.php?page=delivery');
    }
    $GLOBALS['pageTitle'] = 'Buat Surat Jalan — ' . $so['so_number'];

    $items = Database::all(
        'SELECT i.id AS so_item_id, i.product_id, i.qty AS ordered_qty, p.code, p.name, p.unit, p.stock,
                (SELECT COALESCE(SUM(di.qty),0) FROM delivery_order_items di
                 JOIN delivery_orders d ON d.id = di.do_id
                 WHERE d.so_id = i.so_id AND di.product_id = i.product_id AND d.status != \'CANCELLED\') AS delivered_qty
         FROM sales_order_items i JOIN products p ON p.id = i.product_id WHERE i.so_id = ?',
        [$soId]
    );
    ?>
    <div class="card card-primary">
        <div class="card-header"><h3 class="card-title"><i class="fas fa-truck"></i> Surat Jalan untuk <?= e($so['so_number']) ?></h3></div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-6">
                    <table class="table table-borderless table-sm">
                        <tr><td width="120" class="text-muted">Customer</td><td><b><?= e($so['customer_name']) ?></b></td></tr>
                        <tr><td class="text-muted">Alamat</td><td><?= e($so['address']) ?></td></tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <table class="table table-borderless table-sm">
                        <tr><td width="120" class="text-muted">Tanggal SO</td><td><?= fdate($so['order_date']) ?></td></tr>
                        <tr><td class="text-muted">Total SO</td><td><?= money($so['total']) ?></td></tr>
                    </table>
                </div>
            </div>

            <form method="post">
                <input type="hidden" name="so_id" value="<?= $so['id'] ?>">
                <input type="hidden" name="delivery_date" value="<?= date('Y-m-d') ?>">
                <input type="hidden" name="driver" value="<?= e($_GET['driver'] ?? '') ?>">
                <input type="hidden" name="vehicle" value="<?= e($_GET['vehicle'] ?? '') ?>">
                <input type="hidden" name="notes" value="<?= e($_GET['notes'] ?? '') ?>">

                <h5><i class="fas fa-list"></i> Item yang Akan Dikirim</h5>
                <table class="table table-bordered">
                    <thead class="thead-light">
                        <tr>
                            <th>Produk</th><th class="text-right">Dipesan</th><th class="text-right">Terkirim</th><th class="text-right">Sisa</th><th class="text-right">Stok</th><th width="120">Qty Kirim</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($items as $it):
                        $remaining = $it['ordered_qty'] - $it['delivered_qty'];
                    ?>
                        <tr>
                            <td><?= e($it['code']) ?> - <?= e($it['name']) ?></td>
                            <td class="text-right"><?= number_format($it['ordered_qty']) ?></td>
                            <td class="text-right"><?= number_format($it['delivered_qty']) ?></td>
                            <td class="text-right font-weight-bold <?= $remaining > 0 ? 'text-primary' : 'text-success' ?>"><?= number_format($remaining) ?></td>
                            <td class="text-right">
                                <span class="badge badge-<?= $it['stock'] >= $remaining ? 'success' : 'danger' ?>"><?= number_format($it['stock']) ?></span>
                            </td>
                            <td>
                                <input type="number" name="qty[<?= $it['so_item_id'] ?>]" class="form-control form-control-sm qty-input"
                                       min="0" max="<?= $remaining ?>" step="any" value="<?= $remaining ?>"
                                       <?= $remaining <= 0 ? 'disabled' : '' ?>>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="text-right">
                    <a href="index.php?page=delivery" class="btn btn-secondary">Batal</a>
                    <button class="btn btn-primary"><i class="fas fa-truck"></i> Buat Surat Jalan</button>
                </div>
            </form>
        </div>
    </div>
    <?php
}
