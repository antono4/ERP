<?php
// -----------------------------------------------------
// Modul HR: Absensi
// -----------------------------------------------------

$pageTitle = 'Absensi Karyawan';

function module_handle(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $empId = (int)$_POST['employee_id'];
        $date = $_POST['attend_date'];
        $checkIn = $_POST['check_in'] ?: null;
        $checkOut = $_POST['check_out'] ?: null;
        $status = $_POST['status'];
        $notes = trim($_POST['notes'] ?? '');

        Database::query(
            'INSERT INTO attendances (employee_id, attend_date, check_in, check_out, status, notes)
             VALUES (?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE check_in=VALUES(check_in), check_out=VALUES(check_out), status=VALUES(status), notes=VALUES(notes)',
            [$empId, $date, $checkIn, $checkOut, $status, $notes]
        );
        setFlash('success', 'Absensi disimpan.');
        redirect('index.php?page=attendance&month=' . date('Y-m', strtotime($date)));
    }
}

function module_render(): void
{
    $month = $_GET['month'] ?? date('Y-m');
    $employees = Database::all("SELECT * FROM employees WHERE status='ACTIVE' ORDER BY full_name");

    $records = Database::all(
        'SELECT a.*, e.full_name, e.employee_number FROM attendances a
         JOIN employees e ON e.id = a.employee_id
         WHERE DATE_FORMAT(a.attend_date, "%Y-%m") = ?
         ORDER BY a.attend_date DESC, e.full_name',
        [$month]
    );

    $summary = Database::row(
        'SELECT
            SUM(status = "PRESENT") AS present,
            SUM(status = "SICK") AS sick,
            SUM(status = "PERMIT") AS permit,
            SUM(status = "ABSENT") AS absent,
            SUM(status = "LEAVE") AS leave_count
         FROM attendances WHERE DATE_FORMAT(attend_date, "%Y-%m") = ?',
        [$month]
    );

    $statusBadges = ['PRESENT'=>'success','SICK'=>'warning','PERMIT'=>'info','ABSENT'=>'danger','LEAVE'=>'secondary'];
    ?>
    <div class="row mb-3">
        <?php
        $cards = [
            ['Hadir', $summary['present'] ?? 0, 'success'],
            ['Sakit', $summary['sick'] ?? 0, 'warning'],
            ['Izin', $summary['permit'] ?? 0, 'info'],
            ['Alpha', $summary['absent'] ?? 0, 'danger'],
            ['Cuti', $summary['leave_count'] ?? 0, 'secondary'],
        ];
        foreach ($cards as $c): ?>
        <div class="col-md col-6">
            <div class="small-box bg-<?= $c[2] ?> p-2 text-center">
                <h4 class="mb-0"><?= $c[1] ?></h4><small><?= $c[0] ?></small>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="row">
        <div class="col-md-4">
            <div class="card card-primary">
                <div class="card-header"><h3 class="card-title">Input Absensi</h3></div>
                <form method="post">
                    <input type="hidden" name="action" value="save">
                    <div class="card-body">
                        <div class="form-group">
                            <label>Karyawan</label>
                            <select name="employee_id" class="form-control select2" required>
                                <option value="">- Pilih -</option>
                                <?php foreach ($employees as $e): ?>
                                    <option value="<?= $e['id'] ?>"><?= e($e['employee_number']) ?> - <?= e($e['full_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group"><label>Tanggal</label><input type="date" name="attend_date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
                        <div class="row">
                            <div class="col-6"><div class="form-group"><label>Jam Masuk</label><input type="time" name="check_in" class="form-control"></div></div>
                            <div class="col-6"><div class="form-group"><label>Jam Keluar</label><input type="time" name="check_out" class="form-control"></div></div>
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status" class="form-control">
                                <?php foreach (['PRESENT','SICK','PERMIT','ABSENT','LEAVE'] as $s): ?>
                                    <option value="<?= $s ?>"><?= $s ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group"><label>Catatan</label><input type="text" name="notes" class="form-control"></div>
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
                    <h3 class="card-title">Rekap Absensi</h3>
                    <div class="card-tools">
                        <form method="get" class="form-inline">
                            <input type="hidden" name="page" value="attendance">
                            <input type="month" name="month" class="form-control form-control-sm mr-2" value="<?= e($month) ?>">
                            <button class="btn btn-sm btn-primary"><i class="fas fa-search"></i></button>
                        </form>
                    </div>
                </div>
                <div class="card-body">
                    <table class="table table-bordered table-striped datatable">
                        <thead><tr><th>Tanggal</th><th>Karyawan</th><th>Masuk</th><th>Keluar</th><th>Status</th><th>Catatan</th></tr></thead>
                        <tbody>
                        <?php foreach ($records as $r): ?>
                            <tr>
                                <td><?= fdate($r['attend_date']) ?></td>
                                <td><?= e($r['full_name']) ?></td>
                                <td><?= $r['check_in'] ?? '-' ?></td>
                                <td><?= $r['check_out'] ?? '-' ?></td>
                                <td><span class="badge badge-<?= $statusBadges[$r['status']] ?>"><?= $r['status'] ?></span></td>
                                <td><?= e($r['notes']) ?></td>
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
