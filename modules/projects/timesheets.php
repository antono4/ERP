<?php
// -----------------------------------------------------
// Modul Project: Timesheet
// -----------------------------------------------------

$pageTitle = 'Timesheet';

function module_handle(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        Database::query(
            'INSERT INTO project_timesheets (project_id, task_id, employee_id, work_date, hours, description) VALUES (?,?,?,?,?,?)',
            [(int)$_POST['project_id'], (int)$_POST['task_id'] ?: null, (int)$_POST['employee_id'], $_POST['work_date'], (float)$_POST['hours'], trim($_POST['description'] ?? '')]
        );
        setFlash('success', 'Timesheet disimpan.');
        redirect('index.php?page=timesheets');
    } elseif ($action === 'delete') {
        Database::query('DELETE FROM project_timesheets WHERE id = ?', [(int)$_POST['id']]);
        setFlash('success', 'Timesheet dihapus.');
        redirect('index.php?page=timesheets');
    }
}

function module_render(): void
{
    $month = $_GET['month'] ?? date('Y-m');
    $records = Database::all(
        'SELECT ts.*, e.full_name, p.name AS project_name, t.title AS task_title
         FROM project_timesheets ts
         JOIN employees e ON e.id = ts.employee_id
         JOIN projects p ON p.id = ts.project_id
         LEFT JOIN project_tasks t ON t.id = ts.task_id
         WHERE DATE_FORMAT(ts.work_date, "%Y-%m") = ?
         ORDER BY ts.work_date DESC, ts.id DESC',
        [$month]
    );
    $projects = Database::all('SELECT * FROM projects WHERE status IN ("PLANNING","ACTIVE") ORDER BY name');
    $employees = Database::all("SELECT * FROM employees WHERE status='ACTIVE' ORDER BY full_name");
    $totalHours = array_sum(array_column($records, 'hours'));
    ?>
    <div class="row">
        <div class="col-md-4">
            <div class="card card-primary">
                <div class="card-header"><h3 class="card-title">Input Timesheet</h3></div>
                <form method="post">
                    <input type="hidden" name="action" value="save">
                    <div class="card-body">
                        <div class="form-group">
                            <label>Project</label>
                            <select name="project_id" id="tsProject" class="form-control select2" required>
                                <option value="">- Pilih -</option>
                                <?php foreach ($projects as $p): ?>
                                    <option value="<?= $p['id'] ?>"><?= e($p['code']) ?> - <?= e($p['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Task (opsional)</label>
                            <select name="task_id" id="tsTask" class="form-control select2">
                                <option value="">- Pilih project dulu -</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Karyawan</label>
                            <select name="employee_id" class="form-control select2" required>
                                <option value="">- Pilih -</option>
                                <?php foreach ($employees as $e): ?>
                                    <option value="<?= $e['id'] ?>"><?= e($e['full_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-6"><div class="form-group"><label>Tanggal</label><input type="date" name="work_date" class="form-control" value="<?= date('Y-m-d') ?>" required></div></div>
                            <div class="col-6"><div class="form-group"><label>Jam</label><input type="number" name="hours" class="form-control" min="0.25" max="24" step="0.25" value="8" required></div></div>
                        </div>
                        <div class="form-group"><label>Deskripsi Pekerjaan</label><textarea name="description" class="form-control" rows="2"></textarea></div>
                    </div>
                    <div class="card-footer">
                        <button class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Rekap Timesheet — Total: <?= number_format($totalHours, 1) ?> jam</h3>
                    <div class="card-tools">
                        <form method="get" class="form-inline">
                            <input type="hidden" name="page" value="timesheets">
                            <input type="month" name="month" class="form-control form-control-sm mr-2" value="<?= e($month) ?>">
                            <button class="btn btn-sm btn-primary"><i class="fas fa-search"></i></button>
                        </form>
                    </div>
                </div>
                <div class="card-body">
                    <table class="table table-bordered table-striped datatable">
                        <thead><tr><th>Tanggal</th><th>Karyawan</th><th>Project</th><th>Task</th><th class="text-right">Jam</th><th>Deskripsi</th><th width="60">Aksi</th></tr></thead>
                        <tbody>
                        <?php foreach ($records as $r): ?>
                            <tr>
                                <td><?= fdate($r['work_date']) ?></td>
                                <td><?= e($r['full_name']) ?></td>
                                <td><?= e($r['project_name']) ?></td>
                                <td><?= e($r['task_title'] ?? '-') ?></td>
                                <td class="text-right"><?= number_format($r['hours'], 1) ?></td>
                                <td><?= e($r['description']) ?></td>
                                <td>
                                    <form method="post" class="d-inline" onsubmit="return confirm('Hapus?')">
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
