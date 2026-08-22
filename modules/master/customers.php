<?php
// -----------------------------------------------------
// Modul Master: Customer (Business Partner)
// -----------------------------------------------------

$pageTitle = 'Customer';

function module_handle(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $data = [
            trim($_POST['code']), trim($_POST['name']), trim($_POST['email'] ?? ''),
            trim($_POST['phone'] ?? ''), trim($_POST['address'] ?? ''), trim($_POST['city'] ?? ''),
        ];
        if ($id > 0) {
            Database::query('UPDATE customers SET code=?, name=?, email=?, phone=?, address=?, city=? WHERE id=?',
                [...$data, $id]);
            setFlash('success', 'Customer berhasil diupdate.');
        } else {
            Database::query('INSERT INTO customers (code, name, email, phone, address, city) VALUES (?,?,?,?,?,?)', $data);
            setFlash('success', 'Customer berhasil ditambahkan.');
        }
    } elseif ($action === 'delete') {
        Database::query('DELETE FROM customers WHERE id = ?', [(int)$_POST['id']]);
        setFlash('success', 'Customer berhasil dihapus.');
    }
    redirect('index.php?page=customers');
}

function module_render(): void
{
    $items = Database::all('SELECT * FROM customers ORDER BY code');
    $edit = isset($_GET['edit'])
        ? Database::row('SELECT * FROM customers WHERE id = ?', [(int)$_GET['edit']])
        : null;
    ?>
    <div class="row">
        <div class="col-md-4">
            <div class="card card-primary">
                <div class="card-header"><h3 class="card-title"><?= $edit ? 'Edit' : 'Tambah' ?> Customer</h3></div>
                <form method="post">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" value="<?= $edit['id'] ?? 0 ?>">
                    <div class="card-body">
                        <div class="form-group"><label>Kode</label><input type="text" name="code" class="form-control" required value="<?= e($edit['code'] ?? '') ?>"></div>
                        <div class="form-group"><label>Nama Customer</label><input type="text" name="name" class="form-control" required value="<?= e($edit['name'] ?? '') ?>"></div>
                        <div class="form-group"><label>Email</label><input type="email" name="email" class="form-control" value="<?= e($edit['email'] ?? '') ?>"></div>
                        <div class="form-group"><label>Telepon</label><input type="text" name="phone" class="form-control" value="<?= e($edit['phone'] ?? '') ?>"></div>
                        <div class="form-group"><label>Kota</label><input type="text" name="city" class="form-control" value="<?= e($edit['city'] ?? '') ?>"></div>
                        <div class="form-group"><label>Alamat</label><textarea name="address" class="form-control" rows="2"><?= e($edit['address'] ?? '') ?></textarea></div>
                    </div>
                    <div class="card-footer">
                        <button class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
                        <?php if ($edit): ?><a href="index.php?page=customers" class="btn btn-secondary">Batal</a><?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Daftar Customer</h3></div>
                <div class="card-body">
                    <table class="table table-bordered table-striped datatable">
                        <thead><tr><th>Kode</th><th>Nama</th><th>Email</th><th>Telepon</th><th>Kota</th><th width="100">Aksi</th></tr></thead>
                        <tbody>
                        <?php foreach ($items as $c): ?>
                            <tr>
                                <td><?= e($c['code']) ?></td>
                                <td><?= e($c['name']) ?></td>
                                <td><?= e($c['email']) ?></td>
                                <td><?= e($c['phone']) ?></td>
                                <td><?= e($c['city']) ?></td>
                                <td>
                                    <a href="index.php?page=customers&edit=<?= $c['id'] ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
                                    <form method="post" class="d-inline" onsubmit="return confirm('Hapus customer ini?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $c['id'] ?>">
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
