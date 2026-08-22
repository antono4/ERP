<?php
// -----------------------------------------------------
// Modul Project: Detail + Task Management
// -----------------------------------------------------

function module_handle(): void
{
    $id = (int)($_GET['id'] ?? 0);
    $p = Database::row('SELECT code FROM projects WHERE id = ?', [$id]);
    $GLOBALS['pageTitle'] = $p ? 'Project ' . $p['code'] : 'Project';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $action = $_POST['action'] ?? '';

    if ($action === 'add_task') {
        Database::query(
            'INSERT INTO project_tasks (project_id, title, assignee_id, due_date, priority, status, description) VALUES (?,?,?,?,?,?,?)',
            [$id, trim($_POST['title']), (int)$_POST['assignee_id'] ?: null, $_POST['due_date'] ?: null, $_POST['priority'], 'TODO', trim($_POST['description'] ?? '')]
        );
        setFlash('success', 'Task ditambahkan.');
    } elseif ($action === 'update_task') {
        $taskId = (int)$_POST['task_id'];
        $status = $_POST['status'];
        $progress = $status === 'DONE' ? 100 : (int)$_POST['progress'];
        Database::query('UPDATE project_tasks SET status=?, progress=? WHERE id=?', [$status, $progress, $taskId]);
        setFlash('success', 'Task diupdate.');
    } elseif ($action === 'delete_task') {
        Database::query('DELETE FROM project_tasks WHERE id = ?', [(int)$_POST['task_id']]);
        setFlash('success', 'Task dihapus.');
    }
    redirect('index.php?page=project_view&id=' . $id);
}

function module_render(): void
{
    $id = (int)($_GET['id'] ?? 0);
    $project = Database::row(
        'SELECT p.*, c.name AS customer_name, e.full_name AS manager_name
         FROM projects p
         LEFT JOIN customers c ON c.id = p.customer_id
         LEFT JOIN employees e ON e.id = p.manager_id WHERE p.id = ?',
        [$id]
    );
    if (!$project) {
        setFlash('danger', 'Project tidak ditemukan.');
        redirect('index.php?page=projects');
    }
    $GLOBALS['pageTitle'] = 'Project ' . $project['code'];

    $tasks = Database::all(
        'SELECT t.*, e.full_name AS assignee_name FROM project_tasks t
         LEFT JOIN employees e ON e.id = t.assignee_id WHERE t.project_id = ? ORDER BY t.due_date',
        [$id]
    );
    $timesheets = Database::all(
        'SELECT ts.*, e.full_name, t.title AS task_title FROM project_timesheets ts
         JOIN employees e ON e.id = ts.employee_id
         LEFT JOIN project_tasks t ON t.id = ts.task_id
         WHERE ts.project_id = ? ORDER BY ts.work_date DESC LIMIT 20',
        [$id]
    );
    $employees = Database::all("SELECT * FROM employees WHERE status='ACTIVE' ORDER BY full_name");
    $totalHours = array_sum(array_column($timesheets, 'hours'));
    $doneTasks = count(array_filter($tasks, fn($t) => $t['status'] === 'DONE'));
    $pct = count($tasks) > 0 ? round($doneTasks / count($tasks) * 100) : 0;
    ?>
    <div class="row">
        <div class="col-md-4">
            <div class="card card-primary">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-project-diagram"></i> <?= e($project['name']) ?></h3></div>
                <div class="card-body">
                    <table class="table table-borderless table-sm">
                        <tr><td width="120" class="text-muted">Kode</td><td><?= e($project['code']) ?></td></tr>
                        <tr><td class="text-muted">Customer</td><td><?= e($project['customer_name'] ?? '-') ?></td></tr>
                        <tr><td class="text-muted">Manager</td><td><?= e($project['manager_name'] ?? '-') ?></td></tr>
                        <tr><td class="text-muted">Periode</td><td><?= fdate($project['start_date']) ?> - <?= fdate($project['end_date']) ?></td></tr>
                        <tr><td class="text-muted">Budget</td><td><?= money($project['budget']) ?></td></tr>
                        <tr><td class="text-muted">Status</td><td><?= statusBadge($project['status']) ?></td></tr>
                    </table>
                    <hr>
                    <p><strong>Progress:</strong> <?= $doneTasks ?>/<?= count($tasks) ?> task</p>
                    <div class="progress"><div class="progress-bar bg-success" style="width: <?= $pct ?>%"><?= $pct ?>%</div></div>
                    <p class="mt-2"><strong>Total Jam Kerja:</strong> <?= number_format($totalHours, 1) ?> jam</p>
                    <p class="text-muted"><?= nl2br(e($project['description'] ?? '')) ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-tasks"></i> Task</h3>
                    <div class="card-tools">
                        <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#taskModal"><i class="fas fa-plus"></i> Tambah Task</button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm">
                        <thead><tr><th>Task</th><th>Assignee</th><th>Deadline</th><th>Prioritas</th><th>Status</th><th>Progress</th><th width="100">Aksi</th></tr></thead>
                        <tbody>
                        <?php foreach ($tasks as $t): ?>
                            <tr>
                                <td><?= e($t['title']) ?></td>
                                <td><?= e($t['assignee_name'] ?? '-') ?></td>
                                <td><?= fdate($t['due_date']) ?></td>
                                <td><?= statusBadge($t['priority']) ?></td>
                                <td><?= statusBadge($t['status']) ?></td>
                                <td>
                                    <div class="progress progress-sm"><div class="progress-bar" style="width: <?= $t['progress'] ?>%"></div></div>
                                    <small><?= $t['progress'] ?>%</small>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-warning" data-toggle="modal" data-target="#editTask<?= $t['id'] ?>"><i class="fas fa-edit"></i></button>
                                    <form method="post" class="d-inline" onsubmit="return confirm('Hapus task?')">
                                        <input type="hidden" name="action" value="delete_task">
                                        <input type="hidden" name="task_id" value="<?= $t['id'] ?>">
                                        <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($tasks)): ?>
                            <tr><td colspan="7" class="text-center text-muted">Belum ada task</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-clock"></i> Timesheet Terbaru</h3></div>
                <div class="card-body p-0">
                    <table class="table table-sm">
                        <thead><tr><th>Tanggal</th><th>Karyawan</th><th>Task</th><th class="text-right">Jam</th><th>Deskripsi</th></tr></thead>
                        <tbody>
                        <?php foreach ($timesheets as $ts): ?>
                            <tr>
                                <td><?= fdate($ts['work_date']) ?></td>
                                <td><?= e($ts['full_name']) ?></td>
                                <td><?= e($ts['task_title'] ?? '-') ?></td>
                                <td class="text-right"><?= number_format($ts['hours'], 1) ?></td>
                                <td><?= e($ts['description']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($timesheets)): ?>
                            <tr><td colspan="5" class="text-center text-muted">Belum ada timesheet</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="taskModal">
        <div class="modal-dialog">
            <form method="post" class="modal-content">
                <input type="hidden" name="action" value="add_task">
                <div class="modal-header"><h5 class="modal-title">Tambah Task</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
                <div class="modal-body">
                    <div class="form-group"><label>Judul Task</label><input type="text" name="title" class="form-control" required></div>
                    <div class="form-group">
                        <label>Assignee</label>
                        <select name="assignee_id" class="form-control select2">
                            <option value="">- Pilih -</option>
                            <?php foreach ($employees as $e): ?><option value="<?= $e['id'] ?>"><?= e($e['full_name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-6"><div class="form-group"><label>Deadline</label><input type="date" name="due_date" class="form-control"></div></div>
                        <div class="col-6">
                            <div class="form-group">
                                <label>Prioritas</label>
                                <select name="priority" class="form-control">
                                    <?php foreach (['LOW','MEDIUM','HIGH','CRITICAL'] as $p): ?><option value="<?= $p ?>"><?= $p ?></option><?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="form-group"><label>Deskripsi</label><textarea name="description" class="form-control" rows="2"></textarea></div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                </div>
            </form>
        </div>
    </div>

    <?php foreach ($tasks as $t): ?>
    <div class="modal fade" id="editTask<?= $t['id'] ?>">
        <div class="modal-dialog">
            <form method="post" class="modal-content">
                <input type="hidden" name="action" value="update_task">
                <input type="hidden" name="task_id" value="<?= $t['id'] ?>">
                <div class="modal-header"><h5 class="modal-title">Update: <?= e($t['title']) ?></h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" class="form-control">
                            <?php foreach (['TODO','IN_PROGRESS','DONE','CANCELLED'] as $s): ?>
                                <option value="<?= $s ?>" <?= $t['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>Progress (%)</label><input type="number" name="progress" class="form-control" min="0" max="100" value="<?= $t['progress'] ?>"></div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary"><i class="fas fa-save"></i> Update</button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                </div>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
    <?php
}
