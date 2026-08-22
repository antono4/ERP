<?php
// -----------------------------------------------------
// Modul Master: User Management
// -----------------------------------------------------

$pageTitle = 'Manajemen User';

function module_handle(): void
{
    Auth::requireRole(['admin']);
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $username = trim($_POST['username']);
        $fullName = trim($_POST['full_name']);
        $email = trim($_POST['email'] ?? '');
        $role = $_POST['role'];
        $status = isset($_POST['status']) ? 1 : 0;
        $password = $_POST['password'] ?? '';

        if ($id > 0) {
            $sql = 'UPDATE users SET username=?, full_name=?, email=?, role=?, status=? WHERE id=?';
            $params = [$username, $fullName, $email, $role, $status, $id];
            if ($password !== '') {
                $sql = 'UPDATE users SET username=?, full_name=?, email=?, role=?, status=?, password=? WHERE id=?';
                $params = [$username, $fullName, $email, $role, $status, password_hash($password, PASSWORD_DEFAULT), $id];
            }
            Database::query($sql, $params);
            setFlash('success', 'User berhasil diupdate.');
        } else {
            if ($password === '') {
                setFlash('danger', 'Password wajib diisi untuk user baru.');
                redirect('index.php?page=users');
            }
            Database::query(
                'INSERT INTO users (username, password, full_name, email, role, status) VALUES (?,?,?,?,?,?)',
                [$username, password_hash($password, PASSWORD_DEFAULT), $fullName, $email, $role, $status]
            );
            setFlash('success', 'User berhasil ditambahkan.');
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        if ($id === Auth::user()['id']) {
            setFlash('danger', 'Tidak dapat menghapus akun sendiri.');
        } else {
            Database::query('DELETE FROM users WHERE id = ?', [$id]);
            setFlash('success', 'User berhasil dihapus.');
        }
    }
    redirect('index.php?page=users');
}

function module_render(): void
{
    Auth::requireRole(['admin']);
    $items = Database::all('SELECT * FROM users ORDER BY username');
    $edit = isset($_GET['edit'])
        ? Database::row('SELECT * FROM users WHERE id = ?', [(int)$_GET['edit']])
        : null;
    ?>
    <div class="row">
        <div class="col-md-4">
            <div class="card card-primary">
                <div class="card-header"><h3 class="card-title"><?= $edit ? 'Edit' : 'Tambah' ?> User</h3></div>
                <form method="post">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" value="<?= $edit['id'] ?? 0 ?>">
                    <div class="card-body">
                        <div class="form-group"><label>Username</label><input type="text" name="username" class="form-control" required value="<?= e($edit['username'] ?? '') ?>"></div>
                        <div class="form-group"><label>Nama Lengkap</label><input type="text" name="full_name" class="form-control" required value="<?= e($edit['full_name'] ?? '') ?>"></div>
                        <div class="form-group"><label>Email</label><input type="email" name="email" class="form-control" value="<?= e($edit['email'] ?? '') ?>"></div>
                        <div class="form-group">
                            <label>Password <?= $edit ? '<small class="text-muted">(kosongkan jika tidak diubah)</small>' : '' ?></label>
                            <input type="password" name="password" class="form-control" <?= $edit ? '' : 'required' ?>>
                        </div>
                        <div class="form-group">
                            <label>Role</label>
                            <select name="role" class="form-control">
                                <?php foreach (['admin','manager','staff'] as $r): ?>
                                    <option value="<?= $r ?>" <?= ($edit['role'] ?? '') === $r ? 'selected' : '' ?>><?= ucfirst($r) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" name="status" class="form-check-input" id="ustatus" <?= ($edit['status'] ?? 1) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="ustatus">Aktif</label>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
                        <?php if ($edit): ?><a href="index.php?page=users" class="btn btn-secondary">Batal</a><?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Daftar User</h3></div>
                <div class="card-body">
                    <table class="table table-bordered table-striped datatable">
                        <thead><tr><th>Username</th><th>Nama</th><th>Email</th><th>Role</th><th>Status</th><th width="100">Aksi</th></tr></thead>
                        <tbody>
                        <?php foreach ($items as $u): ?>
                            <tr>
                                <td><?= e($u['username']) ?></td>
                                <td><?= e($u['full_name']) ?></td>
                                <td><?= e($u['email']) ?></td>
                                <td><?= statusBadge($u['role']) ?></td>
                                <td><?= $u['status'] ? '<span class="badge badge-success">Aktif</span>' : '<span class="badge badge-secondary">Nonaktif</span>' ?></td>
                                <td>
                                    <a href="index.php?page=users&edit=<?= $u['id'] ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
                                    <form method="post" class="d-inline" onsubmit="return confirm('Hapus user ini?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $u['id'] ?>">
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
