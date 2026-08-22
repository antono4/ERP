<?php
// -----------------------------------------------------
// Modul HR: Jabatan (Position)
// -----------------------------------------------------

$pageTitle = 'Jabatan';

function module_handle(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $data = [trim($_POST['code']), trim($_POST['name']), (float)$_POST['base_salary']];
        if ($id > 0) {
            Database::query('UPDATE positions SET code=?, name=?, base_salary=? WHERE id=?', [...$data, $id]);
        } else {
            Database::query('INSERT INTO positions (code, name, base_salary) VALUES (?,?,?)', $data);
        }
        setFlash('success', 'Jabatan disimpan.');
    } elseif ($action === 'delete') {
        Database::query('DELETE FROM positions WHERE id = ?', [(int)$_POST['id']]);
        setFlash('success', 'Jabatan dihapus.');
    }
    redirect('index.php?page=positions');
}

function module_render(): void
{
    $items = Database::all(
        'SELECT p.*, (SELECT COUNT(*) FROM employees WHERE position_id = p.id) AS emp_count
         FROM positions p ORDER BY p.base_salary DESC'
    );
    $edit = isset($_GET['edit']) ? Database::row('SELECT * FROM positions WHERE id = ?', [(int)$_GET['edit']]) : null;
    ?>
    <div class="row">
        <div class="col-md-4">
            <div class="card card-primary">
                <div class="card-header"><h3 class="card-title"><?= $edit ? 'Edit' : 'Tambah' ?> Jabatan</h3></div>
                <form method="post">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" value="<?= $edit['id'] ?? 0 ?>">
                    <div class="card-body">
                        <div class="form-group"><label>Kode</label><input type="text" name="code" class="form-control" required value="<?= e($edit['code'] ?? '') ?>"></div>
                        <div class="form-group"><label>Nama Jabatan</label><input type="text" name="name" class="form-control" required value="<?= e($edit['name'] ?? '') ?>"></div>
                        <div class="form-group"><label>Gaji Pokok Default</label><input type="number" name="base_salary" class="form-control" min="0" step="any" value="<?= $edit['base_salary'] ?? 0 ?>"></div>
                    </div>
                    <div class="card-footer">
                        <button class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Daftar Jabatan</h3></div>
                <div class="card-body">
                    <table class="table table-bordered table-striped datatable">
                        <thead><tr><th>Kode</th><th>Jabatan</th><th class="text-right">Gaji Pokok</th><th class="text-right">Karyawan</th><th width="100">Aksi</th></tr></thead>
                        <tbody>
                        <?php foreach ($items as $p): ?>
                            <tr>
                                <td><?= e($p['code']) ?></td>
                                <td><?= e($p['name']) ?></td>
                                <td class="text-right"><?= money($p['base_salary']) ?></td>
                                <td class="text-right"><span class="badge badge-info"><?= $p['emp_count'] ?></span></td>
                                <td>
                                    <a href="index.php?page=positions&edit=<?= $p['id'] ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
                                    <form method="post" class="d-inline" onsubmit="return confirm('Hapus jabatan ini?')">
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
        </div>
    </div>
    <?php
}
