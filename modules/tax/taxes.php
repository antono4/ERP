<?php
// -----------------------------------------------------
// Modul Tax: Master Pajak
// -----------------------------------------------------

$pageTitle = 'Master Pajak';

function module_handle(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $data = [trim($_POST['code']), trim($_POST['name']), $_POST['type'], (float)$_POST['rate'], isset($_POST['status']) ? 1 : 0];
        if ($id > 0) {
            Database::query('UPDATE taxes SET code=?, name=?, type=?, rate=?, status=? WHERE id=?', [...$data, $id]);
        } else {
            Database::query('INSERT INTO taxes (code, name, type, rate, status) VALUES (?,?,?,?,?)', $data);
        }
        setFlash('success', 'Pajak disimpan.');
    } elseif ($action === 'delete') {
        Database::query('DELETE FROM taxes WHERE id = ?', [(int)$_POST['id']]);
        setFlash('success', 'Pajak dihapus.');
    }
    redirect('index.php?page=taxes');
}

function module_render(): void
{
    $items = Database::all('SELECT * FROM taxes ORDER BY code');
    $edit = isset($_GET['edit']) ? Database::row('SELECT * FROM taxes WHERE id = ?', [(int)$_GET['edit']]) : null;
    ?>
    <div class="row">
        <div class="col-md-4">
            <div class="card card-primary">
                <div class="card-header"><h3 class="card-title"><?= $edit ? 'Edit' : 'Tambah' ?> Pajak</h3></div>
                <form method="post">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" value="<?= $edit['id'] ?? 0 ?>">
                    <div class="card-body">
                        <div class="form-group"><label>Kode</label><input type="text" name="code" class="form-control" required value="<?= e($edit['code'] ?? '') ?>"></div>
                        <div class="form-group"><label>Nama Pajak</label><input type="text" name="name" class="form-control" required value="<?= e($edit['name'] ?? '') ?>"></div>
                        <div class="form-group">
                            <label>Tipe</label>
                            <select name="type" class="form-control">
                                <?php foreach (['PPN','PPH21','PPH22','PPH23','PPH4_2','BEA_MATERAI'] as $t): ?>
                                    <option value="<?= $t ?>" <?= ($edit['type'] ?? '') === $t ? 'selected' : '' ?>><?= $t ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group"><label>Rate (%)</label><input type="number" name="rate" class="form-control" min="0" max="100" step="0.01" value="<?= $edit['rate'] ?? 0 ?>"></div>
                        <div class="form-check"><input type="checkbox" name="status" class="form-check-input" id="tstatus" <?= ($edit['status'] ?? 1) ? 'checked' : '' ?>><label class="form-check-label" for="tstatus">Aktif</label></div>
                    </div>
                    <div class="card-footer">
                        <button class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Daftar Pajak</h3></div>
                <div class="card-body">
                    <table class="table table-bordered table-striped datatable">
                        <thead><tr><th>Kode</th><th>Nama</th><th>Tipe</th><th class="text-right">Rate</th><th>Status</th><th width="100">Aksi</th></tr></thead>
                        <tbody>
                        <?php foreach ($items as $t): ?>
                            <tr>
                                <td><?= e($t['code']) ?></td>
                                <td><?= e($t['name']) ?></td>
                                <td><?= statusBadge($t['type']) ?></td>
                                <td class="text-right"><?= $t['rate'] ?>%</td>
                                <td><?= $t['status'] ? '<span class="badge badge-success">Aktif</span>' : '<span class="badge badge-secondary">Nonaktif</span>' ?></td>
                                <td>
                                    <a href="index.php?page=taxes&edit=<?= $t['id'] ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
                                    <form method="post" class="d-inline" onsubmit="return confirm('Hapus pajak ini?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $t['id'] ?>">
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
