<?php
// -----------------------------------------------------
// Modul Approval: Rules Multi-Level
// -----------------------------------------------------

$pageTitle = 'Approval Matrix';

function module_handle(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $data = [
            trim($_POST['doc_type']), (float)$_POST['min_amount'],
            $_POST['max_amount'] !== '' ? (float)$_POST['max_amount'] : null,
            (int)$_POST['level'], $_POST['approver_role'],
            isset($_POST['is_active']) ? 1 : 0
        ];
        if ($id > 0) {
            Database::query('UPDATE approval_rules SET doc_type=?, min_amount=?, max_amount=?, level=?, approver_role=?, is_active=? WHERE id=?', [...$data, $id]);
        } else {
            Database::query('INSERT INTO approval_rules (doc_type, min_amount, max_amount, level, approver_role, is_active) VALUES (?,?,?,?,?,?)', $data);
        }
        setFlash('success', 'Rule disimpan.');
    } elseif ($action === 'delete') {
        Database::query('DELETE FROM approval_rules WHERE id = ?', [(int)$_POST['id']]);
        setFlash('success', 'Rule dihapus.');
    }
    redirect('index.php?page=approval_rules');
}

function module_render(): void
{
    $items = Database::all('SELECT * FROM approval_rules ORDER BY doc_type, level, min_amount');
    $edit = isset($_GET['edit']) ? Database::row('SELECT * FROM approval_rules WHERE id = ?', [(int)$_GET['edit']]) : null;
    ?>
    <div class="row">
        <div class="col-md-5">
            <div class="card card-primary">
                <div class="card-header"><h3 class="card-title"><?= $edit ? 'Edit' : 'Tambah' ?> Rule</h3></div>
                <form method="post">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" value="<?= $edit['id'] ?? 0 ?>">
                    <div class="card-body">
                        <div class="form-group">
                            <label>Tipe Dokumen</label>
                            <select name="doc_type" class="form-control">
                                <?php foreach (['PURCHASE','SALES','TRANSFER','PAYROLL','PAYMENT'] as $d): ?>
                                    <option value="<?= $d ?>" <?= ($edit['doc_type'] ?? '') === $d ? 'selected' : '' ?>><?= $d ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-6"><div class="form-group"><label>Min Amount</label><input type="number" name="min_amount" class="form-control" min="0" step="any" value="<?= $edit['min_amount'] ?? 0 ?>"></div></div>
                            <div class="col-6"><div class="form-group"><label>Max Amount</label><input type="number" name="max_amount" class="form-control" min="0" step="any" value="<?= $edit['max_amount'] ?? '' ?>" placeholder="Kosongkan = unlimited"></div></div>
                        </div>
                        <div class="form-group"><label>Level Approval</label><input type="number" name="level" class="form-control" min="1" max="5" value="<?= $edit['level'] ?? 1 ?>"></div>
                        <div class="form-group">
                            <label>Approver Role</label>
                            <select name="approver_role" class="form-control">
                                <option value="staff" <?= ($edit['approver_role'] ?? '') === 'staff' ? 'selected' : '' ?>>Staff</option>
                                <option value="manager" <?= ($edit['approver_role'] ?? '') === 'manager' ? 'selected' : '' ?>>Manager</option>
                                <option value="admin" <?= ($edit['approver_role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
                            </select>
                        </div>
                        <div class="form-check"><input type="checkbox" name="is_active" class="form-check-input" id="r_active" <?= ($edit['is_active'] ?? 1) ? 'checked' : '' ?>><label class="form-check-label" for="r_active">Aktif</label></div>
                    </div>
                    <div class="card-footer">
                        <button class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="col-md-7">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Daftar Approval Rules</h3></div>
                <div class="card-body p-0">
                    <table class="table table-sm">
                        <thead><tr><th>Dokumen</th><th class="text-right">Min</th><th class="text-right">Max</th><th>Level</th><th>Role</th><th>Status</th><th width="100">Aksi</th></tr></thead>
                        <tbody>
                        <?php foreach ($items as $r): ?>
                            <tr>
                                <td><?= statusBadge($r['doc_type']) ?></td>
                                <td class="text-right"><?= money($r['min_amount']) ?></td>
                                <td class="text-right"><?= $r['max_amount'] ? money($r['max_amount']) : 'Unlimited' ?></td>
                                <td><span class="badge badge-info">Lv <?= $r['level'] ?></span></td>
                                <td><?= statusBadge($r['approver_role']) ?></td>
                                <td><?= $r['is_active'] ? '<span class="badge badge-success">Aktif</span>' : '<span class="badge badge-secondary">Nonaktif</span>' ?></td>
                                <td>
                                    <a href="index.php?page=approval_rules&edit=<?= $r['id'] ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
                                    <form method="post" class="d-inline" onsubmit="return confirm('Hapus rule?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $r['id'] ?>">
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
