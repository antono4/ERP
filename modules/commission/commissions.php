<?php
// -----------------------------------------------------
// Modul Commission: Komisi Salesman
// -----------------------------------------------------

$pageTitle = 'Komisi Salesman';

function module_handle(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $action = $_POST['action'] ?? '';

    if ($action === 'calculate') {
        $period = $_POST['period'];
        $employees = Database::all("SELECT * FROM employees WHERE status = 'ACTIVE'");
        $created = 0;

        foreach ($employees as $e) {
            $existing = Database::row('SELECT id FROM commission_transactions WHERE employee_id = ? AND period = ?', [$e['id'], $period]);
            if ($existing) continue;

            // Hitung total penjualan yang di handle karyawan ini (via assigned opportunity atau SO)
            $salesAmount = Database::value(
                "SELECT COALESCE(SUM(s.total),0) FROM sales_orders s
                 JOIN customers c ON c.id = s.customer_id
                 WHERE DATE_FORMAT(s.order_date,'%Y-%m') = ? AND s.status != 'CANCELLED'",
                [$period]
            );
            if ($salesAmount <= 0) continue;

            $rule = Database::row("SELECT * FROM commission_rules WHERE status = 1 ORDER BY base_value DESC LIMIT 1");
            $rate = $rule ? $rule['base_value'] : 2.5;
            $commission = $salesAmount * $rate / 100;

            Database::query(
                'INSERT INTO commission_transactions (commission_number, employee_id, period, base_amount, commission_amount, status, created_by) VALUES (?,?,?,?,?,\'DRAFT\',?)',
                [generateNumber('commission_transactions','commission_number','COM'), $e['id'], $period, $salesAmount, $commission, Auth::user()['id']]
            );
            $created++;
        }
        logActivity('commission', 'CALC_COMMISSION', "Komisi periode {$period}: {$created} karyawan");
        setFlash('success', "Komisi periode {$period} dihitung untuk {$created} karyawan.");
        redirect('index.php?page=commissions');
    }

    if ($action === 'approve') {
        $id = (int)$_POST['id'];
        Database::query("UPDATE commission_transactions SET status='APPROVED' WHERE id=?", [$id]);
        setFlash('success', 'Komisi disetujui.');
        redirect('index.php?page=commissions');
    }

    if ($action === 'pay') {
        $id = (int)$_POST['id'];
        Database::query("UPDATE commission_transactions SET status='PAID', paid_date=CURDATE() WHERE id=?", [$id]);
        setFlash('success', 'Komisi dibayar.');
        redirect('index.php?page=commissions');
    }
}

function module_render(): void
{
    $items = Database::all(
        'SELECT ct.*, e.full_name FROM commission_transactions ct JOIN employees e ON e.id = ct.employee_id ORDER BY ct.period DESC'
    );
    $rules = Database::all('SELECT * FROM commission_rules WHERE status = 1 ORDER BY base_value DESC');
    ?>
    <div class="row mb-3">
        <div class="col-md-4">
            <div class="card card-info">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-percent"></i> Aturan Komisi</h3></div>
                <div class="card-body p-0">
                    <table class="table table-sm">
                        <thead><tr><th>Nama</th><th>Tipe</th><th class="text-right">Rate/Komisi</th></tr></thead>
                        <tbody>
                        <?php foreach ($rules as $r): ?>
                            <tr><td><?= e($r['name']) ?></td><td><?= e($r['type']) ?></td><td class="text-right"><?= $r['base_value'] ?>%</td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Daftar Komisi</h3>
                    <div class="card-tools">
                        <form method="post" class="form-inline">
                            <input type="hidden" name="action" value="calculate">
                            <input type="month" name="period" class="form-control form-control-sm mr-2" value="<?= date('Y-m') ?>">
                            <button class="btn btn-primary btn-sm"><i class="fas fa-calculator"></i> Hitung Komisi</button>
                        </form>
                    </div>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm">
                        <thead><tr><th>No. Komisi</th><th>Karyawan</th><th>Periode</th><th class="text-right">Penjualan</th><th class="text-right">Komisi</th><th>Status</th><th width="100">Aksi</th></tr></thead>
                        <tbody>
                        <?php foreach ($items as $c): ?>
                            <tr>
                                <td><?= e($c['commission_number']) ?></td>
                                <td><?= e($c['full_name']) ?></td>
                                <td><?= e($c['period']) ?></td>
                                <td class="text-right"><?= money($c['base_amount']) ?></td>
                                <td class="text-right font-weight-bold"><?= money($c['commission_amount']) ?></td>
                                <td><?= statusBadge($c['status']) ?></td>
                                <td>
                                    <?php if ($c['status'] === 'DRAFT'): ?>
                                        <form method="post" class="d-inline" onsubmit="return confirm('Setujui?')">
                                            <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                            <button name="action" value="approve" class="btn btn-sm btn-warning"><i class="fas fa-check"></i></button>
                                        </form>
                                    <?php elseif ($c['status'] === 'APPROVED'): ?>
                                        <form method="post" class="d-inline" onsubmit="return confirm('Bayar komisi?')">
                                            <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                            <button name="action" value="pay" class="btn btn-sm btn-success"><i class="fas fa-money-bill-wave"></i></button>
                                        </form>
                                    <?php endif; ?>
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
