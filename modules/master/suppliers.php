<?php
// -----------------------------------------------------
// Modul Master: Supplier (Vendor)
// -----------------------------------------------------

$pageTitle = 'Supplier';

function module_handle(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $data = [
            trim($_POST['code']), trim($_POST['name']), trim($_POST['email'] ?? ''),
            trim($_POST['phone'] ?? ''), trim($_POST['address'] ?? ''),
        ];
        if ($id > 0) {
            Database::query('UPDATE suppliers SET code=?, name=?, email=?, phone=?, address=? WHERE id=?',
                [...$data, $id]);
            setFlash('success', 'Supplier berhasil diupdate.');
        } else {
            Database::query('INSERT INTO suppliers (code, name, email, phone, address) VALUES (?,?,?,?,?)', $data);
            setFlash('success', 'Supplier berhasil ditambahkan.');
        }
    } elseif ($action === 'delete') {
        Database::query('DELETE FROM suppliers WHERE id = ?', [(int)$_POST['id']]);
        setFlash('success', 'Supplier berhasil dihapus.');
    }
    redirect('index.php?page=suppliers');
}

function module_render(): void
{
    $items = Database::all('SELECT * FROM suppliers ORDER BY code');
    $edit = isset($_GET['edit'])
        ? Database::row('SELECT * FROM suppliers WHERE id = ?', [(int)$_GET['edit']])
        : null;
    ?>
    <div class="row">
        <div class="col-md-4">
            <div class="card card-primary">
                <div class="card-header"><h3 class="card-title"><?= $edit ? 'Edit' : 'Tambah' ?> Supplier</h3></div>
                <form method="post">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" value="<?= $edit['id'] ?? 0 ?>">
                    <div class="card-body">
                        <div class="form-group"><label>Kode</label><input type="text" name="code" class="form-control" required value="<?= e($edit['code'] ?? '') ?>"></div>
                        <div class="form-group"><label>Nama Supplier</label><input type="text" name="name" class="form-control" required value="<?= e($edit['name'] ?? '') ?>"></div>
                        <div class="form-group"><label>Email</label><input type="email" name="email" class="form-control" value="<?= e($edit['email'] ?? '') ?>"></div>
                        <div class="form-group"><label>Telepon</label><input type="text" name="phone" class="form-control" value="<?= e($edit['phone'] ?? '') ?>"></div>
                        <div class="form-group"><label>Alamat</label><textarea name="address" class="form-control" rows="2"><?= e($edit['address'] ?? '') ?></textarea></div>
                    </div>
                    <div class="card-footer">
                        <button class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
                        <?php if ($edit): ?><a href="index.php?page=suppliers" class="btn btn-secondary">Batal</a><?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Daftar Supplier</h3></div>
                <div class="card-body">
                    <table class="table table-bordered table-striped datatable">
                        <thead><tr><th>Kode</th><th>Nama</th><th>Email</th><th>Telepon</th><th width="100">Aksi</th></tr></thead>
                        <tbody>
                        <?php foreach ($items as $s): ?>
                            <tr>
                                <td><?= e($s['code']) ?></td>
                                <td><?= e($s['name']) ?></td>
                                <td><?= e($s['email']) ?></td>
                                <td><?= e($s['phone']) ?></td>
                                <td>
                                    <a href="index.php?page=suppliers&edit=<?= $s['id'] ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
                                    <form method="post" class="d-inline" onsubmit="return confirm('Hapus supplier ini?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $s['id'] ?>">
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
