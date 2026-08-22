<?php
// -----------------------------------------------------
// Modul WMS: Gudang
// -----------------------------------------------------

$pageTitle = 'Gudang';

function module_handle(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $data = [trim($_POST['code']), trim($_POST['name']), trim($_POST['address'] ?? ''), isset($_POST['default_flag']) ? 1 : 0];
        if ($data[3]) Database::query('UPDATE warehouses SET default_flag = 0');
        if ($id > 0) {
            Database::query('UPDATE warehouses SET code=?, name=?, address=?, default_flag=? WHERE id=?', [...$data, $id]);
        } else {
            Database::query('INSERT INTO warehouses (code, name, address, default_flag) VALUES (?,?,?,?)', $data);
        }
        setFlash('success', 'Gudang disimpan.');
    } elseif ($action === 'delete') {
        Database::query('DELETE FROM warehouses WHERE id = ?', [(int)$_POST['id']]);
        setFlash('success', 'Gudang dihapus.');
    }
    redirect('index.php?page=warehouses');
}

function module_render(): void
{
    $items = Database::all(
        'SELECT w.*,
            (SELECT COUNT(*) FROM warehouse_stocks WHERE warehouse_id = w.id) AS product_count,
            (SELECT COALESCE(SUM(qty),0) FROM warehouse_stocks WHERE warehouse_id = w.id) AS total_qty
         FROM warehouses w ORDER BY w.code'
    );
    $edit = isset($_GET['edit']) ? Database::row('SELECT * FROM warehouses WHERE id = ?', [(int)$_GET['edit']]) : null;
    ?>
    <div class="row">
        <div class="col-md-4">
            <div class="card card-primary">
                <div class="card-header"><h3 class="card-title"><?= $edit ? 'Edit' : 'Tambah' ?> Gudang</h3></div>
                <form method="post">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" value="<?= $edit['id'] ?? 0 ?>">
                    <div class="card-body">
                        <div class="form-group"><label>Kode</label><input type="text" name="code" class="form-control" required value="<?= e($edit['code'] ?? '') ?>"></div>
                        <div class="form-group"><label>Nama Gudang</label><input type="text" name="name" class="form-control" required value="<?= e($edit['name'] ?? '') ?>"></div>
                        <div class="form-group"><label>Alamat</label><textarea name="address" class="form-control" rows="2"><?= e($edit['address'] ?? '') ?></textarea></div>
                        <div class="form-check">
                            <input type="checkbox" name="default_flag" class="form-check-input" id="wdef" <?= ($edit['default_flag'] ?? 0) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="wdef">Gudang Utama (Default)</label>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Daftar Gudang</h3></div>
                <div class="card-body">
                    <table class="table table-bordered table-striped datatable">
                        <thead><tr><th>Kode</th><th>Nama</th><th>Alamat</th><th class="text-right">Produk</th><th class="text-right">Total Qty</th><th>Default</th><th width="100">Aksi</th></tr></thead>
                        <tbody>
                        <?php foreach ($items as $w): ?>
                            <tr>
                                <td><?= e($w['code']) ?></td>
                                <td><?= e($w['name']) ?></td>
                                <td><?= e($w['address']) ?></td>
                                <td class="text-right"><span class="badge badge-info"><?= $w['product_count'] ?></span></td>
                                <td class="text-right"><?= number_format($w['total_qty']) ?></td>
                                <td><?= $w['default_flag'] ? '<span class="badge badge-success">Utama</span>' : '-' ?></td>
                                <td>
                                    <a href="index.php?page=warehouses&edit=<?= $w['id'] ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
                                    <form method="post" class="d-inline" onsubmit="return confirm('Hapus gudang ini?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $w['id'] ?>">
                                        <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php
}
