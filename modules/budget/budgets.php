<?php
// -----------------------------------------------------
// Modul Budget: Anggaran
// -----------------------------------------------------

$pageTitle = 'Budget Management';

function module_handle(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $data = [trim($_POST['name']), (int)$_POST['fiscal_year'], (int)$_POST['department_id'] ?: null, $_POST['status']];
        if ($id > 0) {
            Database::query('UPDATE budgets SET name=?, fiscal_year=?, department_id=?, status=? WHERE id=?', [...$data, $id]);
        } else {
            Database::query('INSERT INTO budgets (name, fiscal_year, department_id, status, created_by) VALUES (?,?,?,?,?)', [...$data, Auth::user()['id']]);
        }
        setFlash('success', 'Budget disimpan.');
    } elseif ($action === 'delete') {
        Database::query('DELETE FROM budgets WHERE id = ?', [(int)$_POST['id']]);
        setFlash('success', 'Budget dihapus.');
    }
    redirect('index.php?page=budgets');
}

function module_render(): void
{
    $items = Database::all(
        'SELECT b.*, d.name AS dept_name, u.full_name AS creator,
            (SELECT COALESCE(SUM(amount),0) FROM budget_lines WHERE budget_id = b.id) AS total_budget
         FROM budgets b
         LEFT JOIN departments d ON d.id = b.department_id
         LEFT JOIN users u ON u.id = b.created_by
         ORDER BY b.fiscal_year DESC, b.name'
    );
    $departments = Database::all('SELECT * FROM departments ORDER BY name');
    ?>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Daftar Budget</h3>
            <div class="card-tools">
                <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#budgetModal" onclick="resetBudget()"><i class="fas fa-plus"></i> Buat Budget</button>
            </div>
        </div>
        <div class="card-body">
            <table class="table table-bordered table-striped datatable">
                <thead><tr><th>Nama Budget</th><th>Tahun</th><th>Departemen</th><th class="text-right">Total Anggaran</th><th>Status</th><th>Oleh</th><th width="100">Aksi</th></tr></thead>
                <tbody>
                <?php foreach ($items as $b): ?>
                    <tr>
                        <td><a href="index.php?page=budget_view&id=<?= $b['id'] ?>"><?= e($b['name']) ?></a></td>
                        <td><?= $b['fiscal_year'] ?></td>
                        <td><?= e($b['dept_name'] ?? 'Semua') ?></td>
                        <td class="text-right"><?= money($b['total_budget']) ?></td>
                        <td><?= statusBadge($b['status']) ?></td>
                        <td><?= e($b['creator'] ?? '-') ?></td>
                        <td>
                            <a href="index.php?page=budget_view&id=<?= $b['id'] ?>" class="btn btn-sm btn-info"><i class="fas fa-eye"></i></a>
                            <button class="btn btn-sm btn-warning" data-toggle="modal" data-target="#budgetModal" onclick='editBudget(<?= json_encode($b, JSON_HEX_APOS) ?>)'><i class="fas fa-edit"></i></button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="budgetModal">
        <div class="modal-dialog">
            <form method="post" class="modal-content">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="b_id" value="0">
                <div class="modal-header"><h5 class="modal-title">Form Budget</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
                <div class="modal-body">
                    <div class="form-group"><label>Nama Budget</label><input type="text" name="name" id="b_name" class="form-control" required placeholder="cth: Budget Operasional 2026"></div>
                    <div class="form-group"><label>Tahun Fiskal</label><input type="number" name="fiscal_year" id="b_year" class="form-control" min="2020" max="2030" value="<?= date('Y') ?>" required></div>
                    <div class="form-group">
                        <label>Departemen (kosongkan untuk semua)</label>
                        <select name="department_id" id="b_dept" class="form-control select2">
                            <option value="">- Semua Departemen -</option>
                            <?php foreach ($departments as $d): ?><option value="<?= $d['id'] ?>"><?= e($d['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" id="b_status" class="form-control">
                            <option value="DRAFT">DRAFT</option>
                            <option value="ACTIVE">ACTIVE</option>
                            <option value="CLOSED">CLOSED</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                </div>
            </form>
        </div>
    </div>
    <?php
}

function module_scripts(): void
{
    ?>
<script>
function resetBudget() {
    $('#b_id').val(0);
    $('#b_name').val('');
    $('#b_year').val(<?= date('Y') ?>);
    $('#b_dept').val('').trigger('change');
    $('#b_status').val('DRAFT');
}
function editBudget(b) {
    $('#b_id').val(b.id);
    $('#b_name').val(b.name);
    $('#b_year').val(b.fiscal_year);
    $('#b_dept').val(b.department_id).trigger('change');
    $('#b_status').val(b.status);
}
</script>
    <?php
}
