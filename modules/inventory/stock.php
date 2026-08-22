<?php
// -----------------------------------------------------
// Modul Inventory: Stok Barang
// -----------------------------------------------------

$pageTitle = 'Stok Barang';

function module_handle(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $action = $_POST['action'] ?? '';

    if ($action === 'adjust') {
        Auth::requireRole(['admin', 'manager']);
        $productId = (int)$_POST['product_id'];
        $type = $_POST['movement_type'];
        $qty = (float)$_POST['qty'];
        $notes = trim($_POST['notes'] ?? 'Penyesuaian stok');

        if ($qty <= 0) {
            setFlash('danger', 'Qty harus lebih dari 0.');
            redirect('index.php?page=stock');
        }
        $product = Database::row('SELECT * FROM products WHERE id = ?', [$productId]);
        if (!$product) redirect('index.php?page=stock');

        if ($type === 'OUT' && $product['stock'] < $qty) {
            setFlash('danger', 'Stok tidak cukup untuk pengurangan.');
            redirect('index.php?page=stock');
        }

        Database::begin();
        try {
            $delta = $type === 'IN' ? $qty : -$qty;
            Database::query('UPDATE products SET stock = stock + ? WHERE id = ?', [$delta, $productId]);
            Database::query(
                "INSERT INTO stock_movements (product_id, movement_type, qty, reference_type, reference_id, notes, created_by)
                 VALUES (?, ?, ?, 'ADJUSTMENT', NULL, ?, ?)",
                [$productId, $type, $qty, $notes, Auth::user()['id']]
            );
            Database::commit();
            setFlash('success', 'Penyesuaian stok berhasil.');
        } catch (Exception $ex) {
            Database::rollback();
            setFlash('danger', 'Gagal: ' . $ex->getMessage());
        }
        redirect('index.php?page=stock');
    }
}

function module_render(): void
{
    $products = Database::all(
        'SELECT p.*, c.name AS category_name,
                (p.stock * p.purchase_price) AS stock_value
         FROM products p LEFT JOIN categories c ON c.id = p.category_id
         ORDER BY p.code'
    );
    $totalValue = array_sum(array_column($products, 'stock_value'));
    $canAdjust = in_array(Auth::user()['role'], ['admin', 'manager'], true);
    ?>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-warehouse"></i> Posisi Stok</h3>
            <div class="card-tools">
                <span class="badge badge-primary">Nilai Persediaan: <?= money($totalValue) ?></span>
            </div>
        </div>
        <div class="card-body">
            <table class="table table-bordered table-striped datatable">
                <thead>
                    <tr>
                        <th>Kode</th><th>Produk</th><th>Kategori</th><th>Satuan</th>
                        <th class="text-right">Stok</th><th class="text-right">Min</th>
                        <th class="text-right">Harga Beli</th><th class="text-right">Nilai Stok</th>
                        <?php if ($canAdjust): ?><th width="80">Aksi</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($products as $p): ?>
                    <tr>
                        <td><?= e($p['code']) ?></td>
                        <td><?= e($p['name']) ?></td>
                        <td><?= e($p['category_name'] ?? '-') ?></td>
                        <td><?= e($p['unit']) ?></td>
                        <td class="text-right">
                            <span class="badge badge-<?= $p['stock'] <= $p['min_stock'] ? 'danger' : 'success' ?>">
                                <?= number_format($p['stock']) ?>
                            </span>
                        </td>
                        <td class="text-right"><?= number_format($p['min_stock']) ?></td>
                        <td class="text-right"><?= money($p['purchase_price']) ?></td>
                        <td class="text-right"><?= money($p['stock_value']) ?></td>
                        <?php if ($canAdjust): ?>
                        <td>
                            <button class="btn btn-sm btn-warning" data-toggle="modal" data-target="#adjustModal"
                                onclick="setAdjust(<?= $p['id'] ?>, '<?= e($p['name']) ?>')">
                                <i class="fas fa-sliders-h"></i> Sesuaikan
                            </button>
                        </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($canAdjust): ?>
    <div class="modal fade" id="adjustModal">
        <div class="modal-dialog">
            <form method="post" class="modal-content">
                <input type="hidden" name="action" value="adjust">
                <input type="hidden" name="product_id" id="adj_product">
                <div class="modal-header"><h5 class="modal-title">Penyesuaian Stok: <span id="adj_name"></span></h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button></div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Tipe</label>
                        <select name="movement_type" class="form-control">
                            <option value="IN">Tambah Stok (IN)</option>
                            <option value="OUT">Kurangi Stok (OUT)</option>
                        </select>
                    </div>
                    <div class="form-group"><label>Qty</label><input type="number" name="qty" class="form-control" min="0.01" step="any" required></div>
                    <div class="form-group"><label>Alasan</label><input type="text" name="notes" class="form-control" placeholder="cth: Stok opname, barang rusak" required></div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
    <?php
}

function module_scripts(): void
{
    echo "<script>function setAdjust(id, name) { $('#adj_product').val(id); $('#adj_name').text(name); }</script>";
}
