<?php
// -----------------------------------------------------
// Modul System: Activity Log / Audit Trail
// -----------------------------------------------------

$pageTitle = 'Activity Log';

function module_handle(): void
{
    Auth::requireRole(['admin']);
}

function module_render(): void
{
    Auth::requireRole(['admin']);
    $moduleFilter = $_GET['module'] ?? '';
    $userFilter = (int)($_GET['user_id'] ?? 0);

    $sql = 'SELECT l.*, u.full_name, u.username FROM activity_logs l LEFT JOIN users u ON u.id = l.user_id WHERE 1=1';
    $params = [];
    if ($moduleFilter !== '') { $sql .= " AND l.module = ?"; $params[] = $moduleFilter; }
    if ($userFilter > 0) { $sql .= " AND l.user_id = ?"; $params[] = $userFilter; }
    $sql .= " ORDER BY l.created_at DESC LIMIT 500";
    $logs = Database::all($sql, $params);

    $modules = Database::all('SELECT DISTINCT module FROM activity_logs ORDER BY module');
    $users = Database::all('SELECT * FROM users ORDER BY username');

    $actionColors = [
        'CREATE' => 'success', 'UPDATE' => 'warning', 'DELETE' => 'danger',
        'LOGIN' => 'info', 'APPROVE' => 'primary', 'CONFIRM' => 'primary',
        'RECEIVE' => 'success', 'DELIVER' => 'info', 'PAYMENT' => 'success',
        'RETURN' => 'warning', 'OPNAME' => 'info',
    ];
    ?>
    <div class="card">
        <div class="card-header"><h3 class="card-title"><i class="fas fa-history"></i> Activity Log / Audit Trail</h3></div>
        <div class="card-body">
            <form method="get" class="form-inline mb-3">
                <input type="hidden" name="page" value="activity_log">
                <select name="module" class="form-control mr-2">
                    <option value="">Semua Modul</option>
                    <?php foreach ($modules as $m): ?>
                        <option value="<?= e($m['module']) ?>" <?= $moduleFilter === $m['module'] ? 'selected' : '' ?>><?= e($m['module']) ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="user_id" class="form-control mr-2">
                    <option value="0">Semua User</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= $userFilter === (int)$u['id'] ? 'selected' : '' ?>><?= e($u['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
            </form>
            <table class="table table-bordered table-striped datatable">
                <thead>
                    <tr><th>Waktu</th><th>User</th><th>Modul</th><th>Aksi</th><th>Deskripsi</th><th>IP</th></tr>
                </thead>
                <tbody>
                <?php foreach ($logs as $l):
                    $actionKey = strtoupper(explode('_', $l['action'])[0]);
                    $color = $actionColors[$actionKey] ?? 'secondary';
                ?>
                    <tr>
                        <td><?= date('d/m/Y H:i:s', strtotime($l['created_at'])) ?></td>
                        <td><?= e($l['full_name'] ?? 'System') ?></td>
                        <td><span class="badge badge-light"><?= e($l['module']) ?></span></td>
                        <td><span class="badge badge-<?= $color ?>"><?= e($l['action']) ?></span></td>
                        <td><?= e($l['description']) ?></td>
                        <td><?= e($l['ip_address']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}
