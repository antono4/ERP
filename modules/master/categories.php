<?php
// -----------------------------------------------------
// Modul Master: Kategori Produk
// -----------------------------------------------------

$pageTitle = 'Kategori Produk';

function module_handle(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $data = [
            'code' => trim($_POST['code']),
            'name' => trim($_POST['name']),
            'description' => trim($_POST['description'] ?? ''),
        ];
        if ($id > 0) {
            Database::query('UPDATE categories SET code=?, name=?, description=? WHERE id=?',
                [$data['code'], $data['name'], $data['description'], $id]);
            setFlash('success', 'Kategori berhasil diupdate.');
        } else {
            Database::query('INSERT INTO categories (code, name, description) VALUES (?,?,?)',
                [$data['code'], $data['name'], $data['description']]);
            setFlash('success', 'Kategori berhasil ditambahkan.');
        }
    } elseif ($action === 'delete') {
        Database::query('DELETE FROM categories WHERE id = ?', [(int)$_POST['id']]);
        setFlash('success', 'Kategori berhasil dihapus.');
    }
    redirect('index.php?page=categories');
}

function module_render(): void
{
    $items = Database::all('SELECT * FROM categories ORDER BY code');
    $edit = null;
    if (isset($_GET['edit'])) {
        $edit = Database::row('SELECT * FROM categories WHERE id = ?', [(int)$_GET['edit']]);
    }
    ?>
    <div class="row">
        <div class="col-md-4">
            <div class="card card-primary">
                <div class="card-header"><h3 class="card-title"><?= $edit ? 'Edit' : 'Tambah' ?> Kategori</h3></div>
                <form method="post">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" value="<?= $edit['id'] ?? 0 ?>">
                    <div class="card-body">
                        <div class="form-group">
                            <label>Kode</label>
                            <input type="text" name="code" class="form-control" required value="<?= e($edit['code'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Nama Kategori</label>
                            <input type="text" name="name" class="form-control" required value="<?= e($edit['name'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Deskripsi</label>
                            <textarea name="description" class="form-control" rows="2"><?= e($edit['description'] ?? '') ?></textarea>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
                        <?php if ($edit): ?><a href="index.php?page=categories" class="btn btn-secondary">Batal</a><?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Daftar Kategori</h3></div>
                <div class="card-body">
                    <table class="table table-bordered table-striped datatable">
                        <thead>
                            <tr><th>Kode</th><th>Nama</th><th>Deskripsi</th><th width="100">Aksi</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($items as $c): ?>
                            <tr>
                                <td><?= e($c['code']) ?></td>
                                <td><?= e($c['name']) ?></td>
                                <td><?= e($c['description']) ?></td>
                                <td>
                                    <a href="index.php?page=categories&edit=<?= $c['id'] ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
                                    <form method="post" class="d-inline" onsubmit="return confirm('Hapus kategori ini?')">
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
