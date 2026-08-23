<?php
// -----------------------------------------------------
// Modul Branches: Cabang
// -----------------------------------------------------

$pageTitle = 'Cabang';

function module_handle(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $data = [trim($_POST['code']), trim($_POST['name']), trim($_POST['address'] ?? ''), trim($_POST['phone'] ?? ''), (int)$_POST['warehouse_id'] ?: null, isset($_POST['is_head_office']) ? 1 : 0];
        if ($data[5]) Database::query('UPDATE branches SET is_head_office = 0');
        if ($id > 0) {
            Database::query('UPDATE branches SET code=?, name=?, address=?, phone=?, warehouse_id=?, is_head_office=? WHERE id=?', [...$data, $id]);
        } else {
            Database::query('INSERT INTO branches (code, name, address, phone, warehouse_id, is_head_office) VALUES (?,?,?,?,?,?)', $data);
        }
        setFlash('success', 'Cabang disimpan.');
    } elseif ($action === 'delete') {
        Database::query('DELETE FROM branches WHERE id = ?', [(int)$_POST['id']]);
        setFlash('success', 'Cabang dihapus.');
    }
    redirect('index.php?page=branches');
}

function module_render(): void
{
    $items = Database::all(
        'SELECT b.*, w.name AS warehouse_name FROM branches b LEFT JOIN warehouses w ON w.id = b.warehouse_id ORDER BY b.is_head_office DESC, b.code'
    );
    $warehouses = Database::all('SELECT * FROM warehouses ORDER BY name');
    $edit = isset($_GET['edit']) ? Database::row('SELECT * FROM branches WHERE id = ?', [(int)$_GET['edit']]) : null;
    ?>
    <div class="row">
        <div class="col-md-4">
            <div class="card card-primary">
                <div class="card-header"><h3 class="card-title"><?= $edit ? 'Edit' : 'Tambah' ?> Cabang</h3></div>
                <form method="post">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" value="<?= $edit['id'] ?? 0 ?>">
                    <div class="card-body">
                        <div class="form-group"><label>Kode</label><input type="text" name="code" class="form-control" required value="<?= e($edit['code'] ?? '') ?>"></div>
                        <div class="form-group"><label>Nama Cabang</label><input type="text" name="name" class="form-control" required value="<?= e($edit['name'] ?? '') ?>"></div>
                        <div class="form-group"><label>Alamat</label><textarea name="address" class="form-control" rows="2"><?= e($edit['address'] ?? '') ?></textarea></div>
                        <div class="form-group"><label>Telepon</label><input type="text" name="phone" class="form-control" value="<?= e($edit['phone'] ?? '') ?>"></div>
                        <div class="form-group">
                            <label>Gudang Default</label>
                            <select name="warehouse_id" class="form-control select2">
                                <option value="">- Pilih -</option>
                                <?php foreach ($warehouses as $w): ?>
                                    <option value="<?= $w['id'] ?>" <?= ($edit['warehouse_id'] ?? '') == $w['id'] ? 'selected' : '' ?>><?= e($w['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" name="is_head_office" class="form-check-input" id="bhq" <?= ($edit['is_head_office'] ?? 0) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="bhq">Kantor Pusat</label>
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
                <div class="card-header"><h3 class="card-title">Daftar Cabang</h3></div>
                <div class="card-body">
                    <table class="table table-bordered table-striped datatable">
                        <thead><tr><th>Kode</th><th>Nama</th><th>Alamat</th><th>Telepon</th><th>Gudang</th><th>Status</th><th width="100">Aksi</th></tr></thead>
                        <tbody>
                        <?php foreach ($items as $b): ?>
                            <tr>
                                <td><?= e($b['code']) ?></td>
                                <td><?= e($b['name']) ?></td>
                                <td><?= e($b['address']) ?></td>
                                <td><?= e($b['phone']) ?></td>
                                <td><?= e($b['warehouse_name'] ?? '-') ?></td>
                                <td><?= $b['is_head_office'] ? '<span class="badge badge-primary">Pusat</span>' : '<span class="badge badge-info">Cabang</span>' ?></td>
                                <td>
                                    <a href="index.php?page=branches&edit=<?= $b['id'] ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
                                    <form method="post" class="d-inline" onsubmit="return confirm('Hapus cabang ini?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $b['id'] ?>">
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
