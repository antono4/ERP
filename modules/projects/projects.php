<?php
// -----------------------------------------------------
// Modul Project: Daftar Project
// -----------------------------------------------------

$pageTitle = 'Project Management';

function module_handle(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $data = [
            trim($_POST['code']), trim($_POST['name']),
            (int)$_POST['customer_id'] ?: null,
            $_POST['start_date'] ?: null, $_POST['end_date'] ?: null,
            (float)$_POST['budget'], $_POST['status'],
            trim($_POST['description'] ?? ''), (int)$_POST['manager_id'] ?: null,
        ];
        if ($id > 0) {
            Database::query(
                'UPDATE projects SET code=?, name=?, customer_id=?, start_date=?, end_date=?, budget=?, status=?, description=?, manager_id=? WHERE id=?',
                [...$data, $id]
            );
        } else {
            Database::query(
                'INSERT INTO projects (code, name, customer_id, start_date, end_date, budget, status, description, manager_id) VALUES (?,?,?,?,?,?,?,?,?)',
                $data
            );
        }
        logActivity('projects', 'SAVE_PROJECT', "Project {$data[1]} disimpan");
        setFlash('success', 'Project disimpan.');
    } elseif ($action === 'delete') {
        Database::query('DELETE FROM projects WHERE id = ?', [(int)$_POST['id']]);
        setFlash('success', 'Project dihapus.');
    }
    redirect('index.php?page=projects');
}

function module_render(): void
{
    $items = Database::all(
        'SELECT p.*, c.name AS customer_name, e.full_name AS manager_name,
            (SELECT COUNT(*) FROM project_tasks WHERE project_id = p.id) AS task_count,
            (SELECT COUNT(*) FROM project_tasks WHERE project_id = p.id AND status = "DONE") AS done_count,
            (SELECT COALESCE(SUM(hours),0) FROM project_timesheets WHERE project_id = p.id) AS total_hours
         FROM projects p
         LEFT JOIN customers c ON c.id = p.customer_id
         LEFT JOIN employees e ON e.id = p.manager_id
         ORDER BY p.created_at DESC'
    );
    $customers = Database::all('SELECT * FROM customers ORDER BY name');
    $employees = Database::all("SELECT * FROM employees WHERE status='ACTIVE' ORDER BY full_name");
    ?>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Daftar Project</h3>
            <div class="card-tools">
                <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#projModal" onclick="resetProj()"><i class="fas fa-plus"></i> Project Baru</button>
            </div>
        </div>
        <div class="card-body">
            <table class="table table-bordered table-striped datatable">
                <thead>
                    <tr><th>Kode</th><th>Project</th><th>Customer</th><th>Manager</th><th>Progress</th><th class="text-right">Budget</th><th class="text-right">Jam</th><th>Status</th><th width="100">Aksi</th></tr>
                </thead>
                <tbody>
                <?php foreach ($items as $p):
                    $pct = $p['task_count'] > 0 ? round($p['done_count'] / $p['task_count'] * 100) : 0;
                ?>
                    <tr>
                        <td><a href="index.php?page=project_view&id=<?= $p['id'] ?>"><?= e($p['code']) ?></a></td>
                        <td><?= e($p['name']) ?></td>
                        <td><?= e($p['customer_name'] ?? '-') ?></td>
                        <td><?= e($p['manager_name'] ?? '-') ?></td>
                        <td>
                            <div class="progress progress-sm"><div class="progress-bar bg-success" style="width: <?= $pct ?>%"></div></div>
                            <small><?= $p['done_count'] ?>/<?= $p['task_count'] ?> task (<?= $pct ?>%)</small>
                        </td>
                        <td class="text-right"><?= money($p['budget']) ?></td>
                        <td class="text-right"><?= number_format($p['total_hours'], 1) ?></td>
                        <td><?= statusBadge($p['status']) ?></td>
                        <td>
                            <a href="index.php?page=project_view&id=<?= $p['id'] ?>" class="btn btn-sm btn-info"><i class="fas fa-eye"></i></a>
                            <button class="btn btn-sm btn-warning" data-toggle="modal" data-target="#projModal" onclick='editProj(<?= json_encode($p, JSON_HEX_APOS) ?>)'><i class="fas fa-edit"></i></button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="projModal">
        <div class="modal-dialog modal-lg">
            <form method="post" class="modal-content">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="p_id" value="0">
                <div class="modal-header"><h5 class="modal-title">Form Project</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group"><label>Kode</label><input type="text" name="code" id="p_code" class="form-control" required></div>
                            <div class="form-group"><label>Nama Project</label><input type="text" name="name" id="p_name" class="form-control" required></div>
                            <div class="form-group">
                                <label>Customer</label>
                                <select name="customer_id" id="p_cust" class="form-control select2">
                                    <option value="">- Pilih -</option>
                                    <?php foreach ($customers as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Project Manager</label>
                                <select name="manager_id" id="p_mgr" class="form-control select2">
                                    <option value="">- Pilih -</option>
                                    <?php foreach ($employees as $e): ?><option value="<?= $e['id'] ?>"><?= e($e['full_name']) ?></option><?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group"><label>Tanggal Mulai</label><input type="date" name="start_date" id="p_start" class="form-control"></div>
                            <div class="form-group"><label>Tanggal Selesai</label><input type="date" name="end_date" id="p_end" class="form-control"></div>
                            <div class="form-group"><label>Budget</label><input type="number" name="budget" id="p_budget" class="form-control" min="0" step="any" value="0"></div>
                            <div class="form-group">
                                <label>Status</label>
                                <select name="status" id="p_status" class="form-control">
                                    <?php foreach (['PLANNING','ACTIVE','ONHOLD','COMPLETED','CANCELLED'] as $s): ?>
                                        <option value="<?= $s ?>"><?= $s ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="form-group"><label>Deskripsi</label><textarea name="description" id="p_desc" class="form-control" rows="2"></textarea></div>
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
function resetProj() {
    $('#p_id').val(0);
    $('#p_code, #p_name, #p_desc').val('');
    $('#p_cust, #p_mgr').val('').trigger('change');
    $('#p_start, #p_end').val('');
    $('#p_budget').val(0);
    $('#p_status').val('PLANNING');
}
function editProj(p) {
    $('#p_id').val(p.id);
    $('#p_code').val(p.code);
    $('#p_name').val(p.name);
    $('#p_cust').val(p.customer_id).trigger('change');
    $('#p_mgr').val(p.manager_id).trigger('change');
    $('#p_start').val(p.start_date);
    $('#p_end').val(p.end_date);
    $('#p_budget').val(p.budget);
    $('#p_status').val(p.status);
    $('#p_desc').val(p.description);
}
</script>
    <?php
}
