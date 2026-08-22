<?php
// -----------------------------------------------------
// Modul HR: Departemen
// -----------------------------------------------------

$pageTitle = 'Departemen';

function module_handle(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $data = [trim($_POST['code']), trim($_POST['name']), (int)$_POST['parent_id'] ?: null];
        if ($id > 0) {
            Database::query('UPDATE departments SET code=?, name=?, parent_id=? WHERE id=?', [...$data, $id]);
        } else {
            Database::query('INSERT INTO departments (code, name, parent_id) VALUES (?,?,?)', $data);
        }
        setFlash('success', 'Departemen disimpan.');
    } elseif ($action === 'delete') {
        Database::query('DELETE FROM departments WHERE id = ?', [(int)$_POST['id']]);
        setFlash('success', 'Departemen dihapus.');
    }
    redirect('index.php?page=departments');
}

function module_render(): void
{
    $items = Database::all(
        'SELECT d.*, p.name AS parent_name,
            (SELECT COUNT(*) FROM employees WHERE department_id = d.id) AS emp_count
         FROM departments d LEFT JOIN departments p ON p.id = d.parent_id ORDER BY d.code'
    );
    $all = Database::all('SELECT id, name FROM departments ORDER BY name');
    $edit = isset($_GET['edit']) ? Database::row('SELECT * FROM departments WHERE id = ?', [(int)$_GET['edit']]) : null;
    ?>
    <div class="row">
        <div class="col-md-4">
            <div class="card card-primary">
                <div class="card-header"><h3 class="card-title"><?= $edit ? 'Edit' : 'Tambah' ?> Departemen</h3></div>
                <form method="post">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" value="<?= $edit['id'] ?? 0 ?>">
                    <div class="card-body">
                        <div class="form-group"><label>Kode</label><input type="text" name="code" class="form-control" required value="<?= e($edit['code'] ?? '') ?>"></div>
                        <div class="form-group"><label>Nama</label><input type="text" name="name" class="form-control" required value="<?= e($edit['name'] ?? '') ?>"></div>
                        <div class="form-group">
                            <label>Parent Departemen</label>
                            <select name="parent_id" class="form-control">
                                <option value="">- Tidak ada -</option>
                                <?php foreach ($all as $d): ?>
                                    <option value="<?= $d['id'] ?>" <?= ($edit['parent_id'] ?? '') == $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
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
                <div class="card-header"><h3 class="card-title">Daftar Departemen</h3></div>
                <div class="card-body">
                    <table class="table table-bordered table-striped datatable">
                        <thead><tr><th>Kode</th><th>Nama</th><th>Parent</th><th class="text-right">Karyawan</th><th width="100">Aksi</th></tr></thead>
                        <tbody>
                        <?php foreach ($items as $d): ?>
                            <tr>
                                <td><?= e($d['code']) ?></td>
                                <td><?= e($d['name']) ?></td>
                                <td><?= e($d['parent_name'] ?? '-') ?></td>
                                <td class="text-right"><span class="badge badge-info"><?= $d['emp_count'] ?></span></td>
                                <td>
                                    <a href="index.php?page=departments&edit=<?= $d['id'] ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
                                    <form method="post" class="d-inline" onsubmit="return confirm('Hapus departemen ini?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $d['id'] ?>">
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
