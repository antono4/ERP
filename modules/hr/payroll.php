<?php
// -----------------------------------------------------
// Modul HR: Payroll (Gaji)
// -----------------------------------------------------

$pageTitle = 'Payroll';

function module_handle(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $period = $_POST['period'];
        $date = $_POST['payroll_date'];

        $existing = Database::row('SELECT id FROM payrolls WHERE period = ?', [$period]);
        if ($existing) {
            setFlash('warning', "Payroll periode {$period} sudah ada.");
            redirect('index.php?page=payroll');
        }

        $employees = Database::all("SELECT * FROM employees WHERE status = 'ACTIVE'");
        if (empty($employees)) {
            setFlash('danger', 'Tidak ada karyawan aktif.');
            redirect('index.php?page=payroll');
        }

        Database::begin();
        try {
            $pNo = generateNumber('payrolls', 'payroll_number', 'PRL');
            $total = 0;
            foreach ($employees as $e) {
                $present = (int)Database::value(
                    "SELECT COUNT(*) FROM attendances WHERE employee_id = ? AND DATE_FORMAT(attend_date,'%Y-%m') = ? AND status = 'PRESENT'",
                    [$e['id'], $period]
                );
                $workingDays = 22;
                $attendanceRatio = $present > 0 ? min($present / $workingDays, 1) : 1;
                $deductions = $present > 0 ? round($e['base_salary'] * (1 - $attendanceRatio)) : 0;
                $net = $e['base_salary'] - $deductions;
                $total += $net;
            }
            Database::query(
                'INSERT INTO payrolls (payroll_number, period, payroll_date, total, status, created_by) VALUES (?,?,?,?,\'DRAFT\',?)',
                [$pNo, $period, $date, $total, Auth::user()['id']]
            );
            $payrollId = (int)Database::lastId();
            foreach ($employees as $e) {
                $present = (int)Database::value(
                    "SELECT COUNT(*) FROM attendances WHERE employee_id = ? AND DATE_FORMAT(attend_date,'%Y-%m') = ? AND status = 'PRESENT'",
                    [$e['id'], $period]
                );
                $workingDays = 22;
                $attendanceRatio = $present > 0 ? min($present / $workingDays, 1) : 1;
                $deductions = $present > 0 ? round($e['base_salary'] * (1 - $attendanceRatio)) : 0;
                $net = $e['base_salary'] - $deductions;
                Database::query(
                    'INSERT INTO payroll_items (payroll_id, employee_id, base_salary, allowances, deductions, net_salary, notes) VALUES (?,?,?,0,?,?,?)',
                    [$payrollId, $e['id'], $e['base_salary'], $deductions, $net, $present > 0 ? "Hadir {$present} hari" : 'Tidak ada data absen']
                );
            }
            Database::commit();
            logActivity('hr', 'CREATE_PAYROLL', "Payroll {$pNo} periode {$period}");
            setFlash('success', "Payroll {$pNo} dibuat untuk " . count($employees) . " karyawan.");
            redirect('index.php?page=payroll_view&id=' . $payrollId);
        } catch (Exception $ex) {
            Database::rollback();
            setFlash('danger', 'Gagal: ' . $ex->getMessage());
        }
        redirect('index.php?page=payroll');
    }

    if ($action === 'approve') {
        $id = (int)$_POST['id'];
        Auth::requireRole(['admin', 'manager']);
        Database::query("UPDATE payrolls SET status='APPROVED' WHERE id=? AND status='DRAFT'", [$id]);
        setFlash('success', 'Payroll disetujui.');
        redirect('index.php?page=payroll_view&id=' . $id);
    }

    if ($action === 'pay') {
        $id = (int)$_POST['id'];
        $payroll = Database::row('SELECT * FROM payrolls WHERE id = ?', [$id]);
        if (!$payroll || $payroll['status'] !== 'APPROVED') redirect('index.php?page=payroll');

        Database::begin();
        try {
            Database::query("UPDATE payrolls SET status='PAID' WHERE id=?", [$id]);
            // Jurnal: Biaya Gaji (D) / Kas (K)
            $exp = Database::value("SELECT id FROM accounts WHERE code='5-1200'");
            $cash = Database::value("SELECT id FROM accounts WHERE code='1-1100'");
            if ($exp && $cash) {
                $jeNo = generateNumber('journal_entries', 'entry_number', 'JE');
                Database::query(
                    'INSERT INTO journal_entries (entry_number, entry_date, description, reference, created_by) VALUES (?,?,?,?,?)',
                    [$jeNo, $payroll['payroll_date'], "Pembayaran gaji periode {$payroll['period']}", $payroll['payroll_number'], Auth::user()['id']]
                );
                $jeId = (int)Database::lastId();
                Database::query('INSERT INTO journal_entry_lines (journal_id, account_id, debit, credit) VALUES (?,?,?,0)', [$jeId, $exp, $payroll['total']]);
                Database::query('INSERT INTO journal_entry_lines (journal_id, account_id, debit, credit) VALUES (?,?,0,?)', [$jeId, $cash, $payroll['total']]);
            }
            Database::commit();
            logActivity('hr', 'PAY_PAYROLL', "Payroll {$payroll['payroll_number']} dibayar");
            setFlash('success', "Payroll {$payroll['payroll_number']} dibayar. Jurnal gaji dibuat.");
        } catch (Exception $ex) {
            Database::rollback();
            setFlash('danger', 'Gagal: ' . $ex->getMessage());
        }
        redirect('index.php?page=payroll_view&id=' . $id);
    }

    if ($action === 'cancel') {
        $id = (int)$_POST['id'];
        Database::query("UPDATE payrolls SET status='CANCELLED' WHERE id=? AND status IN ('DRAFT','APPROVED')", [$id]);
        setFlash('warning', 'Payroll dibatalkan.');
        redirect('index.php?page=payroll');
    }
}

function module_render(): void
{
    $payrolls = Database::all(
        'SELECT p.*, u.full_name AS creator,
            (SELECT COUNT(*) FROM payroll_items WHERE payroll_id = p.id) AS emp_count
         FROM payrolls p LEFT JOIN users u ON u.id = p.created_by
         ORDER BY p.period DESC'
    );
    ?>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Daftar Payroll</h3>
            <div class="card-tools">
                <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#createPayrollModal"><i class="fas fa-plus"></i> Buat Payroll</button>
            </div>
        </div>
        <div class="card-body">
            <table class="table table-bordered table-striped datatable">
                <thead><tr><th>No. Payroll</th><th>Periode</th><th>Tanggal</th><th class="text-right">Karyawan</th><th class="text-right">Total</th><th>Status</th><th>Oleh</th><th width="80">Aksi</th></tr></thead>
                <tbody>
                <?php foreach ($payrolls as $p): ?>
                    <tr>
                        <td><a href="index.php?page=payroll_view&id=<?= $p['id'] ?>"><?= e($p['payroll_number']) ?></a></td>
                        <td><?= e($p['period']) ?></td>
                        <td><?= fdate($p['payroll_date']) ?></td>
                        <td class="text-right"><span class="badge badge-info"><?= $p['emp_count'] ?></span></td>
                        <td class="text-right"><?= money($p['total']) ?></td>
                        <td><?= statusBadge($p['status']) ?></td>
                        <td><?= e($p['creator'] ?? '-') ?></td>
                        <td><a href="index.php?page=payroll_view&id=<?= $p['id'] ?>" class="btn btn-sm btn-info"><i class="fas fa-eye"></i></a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="createPayrollModal">
        <div class="modal-dialog">
            <form method="post" class="modal-content">
                <input type="hidden" name="action" value="create">
                <div class="modal-header"><h5 class="modal-title">Buat Payroll Baru</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
                <div class="modal-body">
                    <div class="form-group"><label>Periode</label><input type="month" name="period" class="form-control" value="<?= date('Y-m') ?>" required></div>
                    <div class="form-group"><label>Tanggal Payroll</label><input type="date" name="payroll_date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
                    <p class="text-muted"><small>Sistem akan menghitung gaji semua karyawan aktif, dengan potongan proporsional berdasarkan data absensi bulan tersebut.</small></p>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary"><i class="fas fa-calculator"></i> Hitung & Buat</button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                </div>
            </form>
        </div>
    </div>
    <?php
}
