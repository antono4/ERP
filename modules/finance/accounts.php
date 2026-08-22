<?php
// -----------------------------------------------------
// Modul Finance: Chart of Accounts
// -----------------------------------------------------

$pageTitle = 'Chart of Accounts';

function module_handle(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $data = [trim($_POST['code']), trim($_POST['name']), $_POST['type']];
        if ($id > 0) {
            Database::query('UPDATE accounts SET code=?, name=?, type=? WHERE id=?', [...$data, $id]);
            setFlash('success', 'Akun berhasil diupdate.');
        } else {
            Database::query('INSERT INTO accounts (code, name, type) VALUES (?,?,?)', $data);
            setFlash('success', 'Akun berhasil ditambahkan.');
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        $used = Database::value('SELECT COUNT(*) FROM journal_entry_lines WHERE account_id = ?', [$id]);
        if ($used > 0) {
            setFlash('danger', 'Akun sudah dipakai di jurnal, tidak bisa dihapus.');
        } else {
            Database::query('DELETE FROM accounts WHERE id = ?', [$id]);
            setFlash('success', 'Akun berhasil dihapus.');
        }
    }
    redirect('index.php?page=accounts');
}

function module_render(): void
{
    $items = Database::all(
        'SELECT a.*,
            COALESCE(SUM(l.debit),0) AS total_debit,
            COALESCE(SUM(l.credit),0) AS total_credit
         FROM accounts a
         LEFT JOIN journal_entry_lines l ON l.account_id = a.id
         GROUP BY a.id ORDER BY a.code'
    );
    $edit = isset($_GET['edit'])
        ? Database::row('SELECT * FROM accounts WHERE id = ?', [(int)$_GET['edit']])
        : null;
    $types = ['ASSET','LIABILITY','EQUITY','REVENUE','EXPENSE'];
    ?>
    <div class="row">
        <div class="col-md-4">
            <div class="card card-primary">
                <div class="card-header"><h3 class="card-title"><?= $edit ? 'Edit' : 'Tambah' ?> Akun</h3></div>
                <form method="post">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" value="<?= $edit['id'] ?? 0 ?>">
                    <div class="card-body">
                        <div class="form-group"><label>Kode Akun</label><input type="text" name="code" class="form-control" required value="<?= e($edit['code'] ?? '') ?>" placeholder="cth: 1-1000"></div>
                        <div class="form-group"><label>Nama Akun</label><input type="text" name="name" class="form-control" required value="<?= e($edit['name'] ?? '') ?>"></div>
                        <div class="form-group">
                            <label>Tipe</label>
                            <select name="type" class="form-control">
                                <?php foreach ($types as $t): ?>
                                    <option value="<?= $t ?>" <?= ($edit['type'] ?? '') === $t ? 'selected' : '' ?>><?= $t ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
                        <?php if ($edit): ?><a href="index.php?page=accounts" class="btn btn-secondary">Batal</a><?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Daftar Akun</h3></div>
                <div class="card-body">
                    <table class="table table-bordered table-striped datatable">
                        <thead><tr><th>Kode</th><th>Nama</th><th>Tipe</th><th class="text-right">Debit</th><th class="text-right">Kredit</th><th class="text-right">Saldo</th><th width="100">Aksi</th></tr></thead>
                        <tbody>
                        <?php foreach ($items as $a):
                            $balance = in_array($a['type'], ['ASSET','EXPENSE'], true)
                                ? $a['total_debit'] - $a['total_credit']
                                : $a['total_credit'] - $a['total_debit'];
                        ?>
                            <tr>
                                <td><?= e($a['code']) ?></td>
                                <td><?= e($a['name']) ?></td>
                                <td><?= statusBadge($a['type']) ?></td>
                                <td class="text-right"><?= money($a['total_debit']) ?></td>
                                <td class="text-right"><?= money($a['total_credit']) ?></td>
                                <td class="text-right font-weight-bold"><?= money($balance) ?></td>
                                <td>
                                    <a href="index.php?page=accounts&edit=<?= $a['id'] ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
                                    <form method="post" class="d-inline" onsubmit="return confirm('Hapus akun ini?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $a['id'] ?>">
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
