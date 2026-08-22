<?php
// -----------------------------------------------------
// Modul Master: Produk (Item Master)
// -----------------------------------------------------

$pageTitle = 'Produk';

function module_handle(): void
{
    if (isset($_GET['export']) && $_GET['export'] === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="produk_' . date('Ymd') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Kode', 'Nama', 'Kategori', 'Satuan', 'Harga Beli', 'Harga Jual', 'Stok', 'Min Stok', 'Status']);
        $rows = Database::all(
            'SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id ORDER BY p.code'
        );
        foreach ($rows as $r) {
            fputcsv($out, [$r['code'], $r['name'], $r['category_name'], $r['unit'], $r['purchase_price'], $r['selling_price'], $r['stock'], $r['min_stock'], $r['status'] ? 'Aktif' : 'Nonaktif']);
        }
        fclose($out);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $data = [
            'code' => trim($_POST['code']),
            'name' => trim($_POST['name']),
            'category_id' => (int)$_POST['category_id'] ?: null,
            'unit' => trim($_POST['unit']),
            'purchase_price' => (float)$_POST['purchase_price'],
            'selling_price' => (float)$_POST['selling_price'],
            'stock' => (float)$_POST['stock'],
            'min_stock' => (float)$_POST['min_stock'],
            'status' => isset($_POST['status']) ? 1 : 0,
        ];
        if ($id > 0) {
            Database::query(
                'UPDATE products SET code=?, name=?, category_id=?, unit=?, purchase_price=?, selling_price=?, stock=?, min_stock=?, status=? WHERE id=?',
                [$data['code'], $data['name'], $data['category_id'], $data['unit'], $data['purchase_price'],
                 $data['selling_price'], $data['stock'], $data['min_stock'], $data['status'], $id]
            );
            setFlash('success', 'Produk berhasil diupdate.');
        } else {
            Database::query(
                'INSERT INTO products (code, name, category_id, unit, purchase_price, selling_price, stock, min_stock, status) VALUES (?,?,?,?,?,?,?,?,?)',
                [$data['code'], $data['name'], $data['category_id'], $data['unit'], $data['purchase_price'],
                 $data['selling_price'], $data['stock'], $data['min_stock'], $data['status']]
            );
            setFlash('success', 'Produk berhasil ditambahkan.');
        }
    } elseif ($action === 'delete') {
        Database::query('DELETE FROM products WHERE id = ?', [(int)$_POST['id']]);
        setFlash('success', 'Produk berhasil dihapus.');
    }
    redirect('index.php?page=products');
}

function module_render(): void
{
    $products = Database::all(
        'SELECT p.*, c.name AS category_name FROM products p
         LEFT JOIN categories c ON c.id = p.category_id ORDER BY p.code'
    );
    $categories = Database::all('SELECT * FROM categories ORDER BY name');
    $edit = null;
    if (isset($_GET['edit'])) {
        $edit = Database::row('SELECT * FROM products WHERE id = ?', [(int)$_GET['edit']]);
    }
    ?>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Daftar Produk</h3>
            <div class="card-tools">
                <a href="index.php?page=products&export=csv" class="btn btn-success btn-sm"><i class="fas fa-file-excel"></i> Export CSV</a>
                <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#productModal" onclick="resetForm()">
                    <i class="fas fa-plus"></i> Tambah Produk
                </button>
            </div>
        </div>
        <div class="card-body">
            <table class="table table-bordered table-striped datatable">
                <thead>
                    <tr>
                        <th>Kode</th><th>Nama Produk</th><th>Kategori</th><th>Satuan</th>
                        <th class="text-right">Harga Beli</th><th class="text-right">Harga Jual</th>
                        <th class="text-right">Stok</th><th>Status</th><th width="100">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($products as $p): ?>
                    <tr>
                        <td><?= e($p['code']) ?></td>
                        <td><?= e($p['name']) ?></td>
                        <td><?= e($p['category_name'] ?? '-') ?></td>
                        <td><?= e($p['unit']) ?></td>
                        <td class="text-right"><?= money($p['purchase_price']) ?></td>
                        <td class="text-right"><?= money($p['selling_price']) ?></td>
                        <td class="text-right">
                            <span class="badge badge-<?= $p['stock'] <= $p['min_stock'] ? 'danger' : 'success' ?>">
                                <?= number_format($p['stock']) ?>
                            </span>
                        </td>
                        <td><?= $p['status'] ? '<span class="badge badge-success">Aktif</span>' : '<span class="badge badge-secondary">Nonaktif</span>' ?></td>
                        <td>
                            <button class="btn btn-sm btn-warning" data-toggle="modal" data-target="#productModal"
                                onclick='editForm(<?= json_encode($p, JSON_HEX_APOS) ?>)'><i class="fas fa-edit"></i></button>
                            <form method="post" class="d-inline" onsubmit="return confirm('Hapus produk ini?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="productModal">
        <div class="modal-dialog modal-lg">
            <form method="post" class="modal-content">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="f_id" value="0">
                <div class="modal-header">
                    <h5 class="modal-title">Form Produk</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group"><label>Kode Produk</label><input type="text" name="code" id="f_code" class="form-control" required></div>
                            <div class="form-group"><label>Nama Produk</label><input type="text" name="name" id="f_name" class="form-control" required></div>
                            <div class="form-group">
                                <label>Kategori</label>
                                <select name="category_id" id="f_category" class="form-control select2">
                                    <option value="">- Pilih Kategori -</option>
                                    <?php foreach ($categories as $c): ?>
                                        <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group"><label>Satuan</label><input type="text" name="unit" id="f_unit" class="form-control" value="PCS" required></div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group"><label>Harga Beli</label><input type="number" name="purchase_price" id="f_buy" class="form-control" min="0" step="0.01" value="0" required></div>
                            <div class="form-group"><label>Harga Jual</label><input type="number" name="selling_price" id="f_sell" class="form-control" min="0" step="0.01" value="0" required></div>
                            <div class="form-group"><label>Stok Awal</label><input type="number" name="stock" id="f_stock" class="form-control" min="0" step="0.01" value="0" required></div>
                            <div class="form-group"><label>Stok Minimum</label><input type="number" name="min_stock" id="f_min" class="form-control" min="0" step="0.01" value="0" required></div>
                            <div class="form-check mt-4">
                                <input type="checkbox" name="status" id="f_status" class="form-check-input" checked>
                                <label class="form-check-label" for="f_status">Aktif</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                </div>
            </form>
        </div>
    </div>
    <?php
}

function module_scripts(): void
{
    ?>
<script>
function resetForm() {
    $('#f_id').val(0);
    $('#f_code, #f_name').val('');
    $('#f_category').val('').trigger('change');
    $('#f_unit').val('PCS');
    $('#f_buy, #f_sell, #f_stock, #f_min').val(0);
    $('#f_status').prop('checked', true);
}
function editForm(p) {
    $('#f_id').val(p.id);
    $('#f_code').val(p.code);
    $('#f_name').val(p.name);
    $('#f_category').val(p.category_id).trigger('change');
    $('#f_unit').val(p.unit);
    $('#f_buy').val(p.purchase_price);
    $('#f_sell').val(p.selling_price);
    $('#f_stock').val(p.stock);
    $('#f_min').val(p.min_stock);
    $('#f_status').prop('checked', p.status == 1);
}
</script>
    <?php
}
