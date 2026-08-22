<?php
// -----------------------------------------------------
// Modul Budget: Detail Budget vs Actual
// -----------------------------------------------------

function module_handle(): void
{
    $id = (int)($_GET['id'] ?? 0);
    $b = Database::row('SELECT name FROM budgets WHERE id = ?', [$id]);
    $GLOBALS['pageTitle'] = $b ? 'Budget: ' . $b['name'] : 'Budget';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_lines') {
        $accountIds = $_POST['account_id'] ?? [];
        $months = $_POST['month'] ?? [];
        $amounts = $_POST['amount'] ?? [];

        Database::query('DELETE FROM budget_lines WHERE budget_id = ?', [$id]);
        $saved = 0;
        foreach ($accountIds as $i => $aid) {
            $aid = (int)$aid;
            $month = (int)($months[$i] ?? 0);
            $amount = (float)($amounts[$i] ?? 0);
            if ($aid > 0 && $month >= 1 && $month <= 12 && $amount > 0) {
                Database::query('INSERT INTO budget_lines (budget_id, account_id, month, amount) VALUES (?,?,?,?)', [$id, $aid, $month, $amount]);
                $saved++;
            }
        }
        setFlash('success', "{$saved} baris budget disimpan.");
        redirect('index.php?page=budget_view&id=' . $id);
    }
}

function module_render(): void
{
    $id = (int)($_GET['id'] ?? 0);
    $budget = Database::row(
        'SELECT b.*, d.name AS dept_name FROM budgets b LEFT JOIN departments d ON d.id = b.department_id WHERE b.id = ?',
        [$id]
    );
    if (!$budget) {
        setFlash('danger', 'Budget tidak ditemukan.');
        redirect('index.php?page=budgets');
    }
    $GLOBALS['pageTitle'] = 'Budget: ' . $budget['name'];

    $lines = Database::all(
        'SELECT bl.*, a.code, a.name, a.type FROM budget_lines bl JOIN accounts a ON a.id = bl.account_id WHERE bl.budget_id = ? ORDER BY a.code, bl.month',
        [$id]
    );
    $accounts = Database::all("SELECT * FROM accounts WHERE type IN ('EXPENSE','REVENUE') ORDER BY code");

    // Actual per account per month dari jurnal
    $actuals = [];
    $actualRows = Database::all(
        'SELECT l.account_id, MONTH(j.entry_date) AS month,
            SUM(CASE WHEN a.type = "EXPENSE" THEN l.debit - l.credit ELSE l.credit - l.debit END) AS actual
         FROM journal_entry_lines l
         JOIN journal_entries j ON j.id = l.journal_id
         JOIN accounts a ON a.id = l.account_id
         WHERE YEAR(j.entry_date) = ? AND a.type IN ("EXPENSE","REVENUE")
         GROUP BY l.account_id, MONTH(j.entry_date)',
        [$budget['fiscal_year']]
    );
    foreach ($actualRows as $ar) {
        $actuals[$ar['account_id']][$ar['month']] = $ar['actual'];
    }

    // Group by account
    $byAccount = [];
    foreach ($lines as $l) {
        $byAccount[$l['account_id']]['info'] = $l;
        $byAccount[$l['account_id']]['months'][$l['month']] = $l['amount'];
    }
    $totalBudget = array_sum(array_column($lines, 'amount'));
    $totalActual = 0;
    foreach ($actuals as $months) { $totalActual += array_sum($months); }
    ?>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-chart-pie"></i> <?= e($budget['name']) ?> — <?= $budget['fiscal_year'] ?> <?= statusBadge($budget['status']) ?>
            </h3>
            <div class="card-tools">
                <a href="index.php?page=budgets" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
            </div>
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-4">
                    <div class="info-box bg-primary"><span class="info-box-icon"><i class="fas fa-wallet"></i></span>
                        <div class="info-box-content"><span class="info-box-text">Total Anggaran</span><span class="info-box-number"><?= money($totalBudget) ?></span></div></div>
                </div>
                <div class="col-md-4">
                    <div class="info-box bg-info"><span class="info-box-icon"><i class="fas fa-chart-line"></i></span>
                        <div class="info-box-content"><span class="info-box-text">Realisasi (Actual)</span><span class="info-box-number"><?= money($totalActual) ?></span></div></div>
                </div>
                <div class="col-md-4">
                    <div class="info-box bg-<?= $totalBudget - $totalActual >= 0 ? 'success' : 'danger' ?>"><span class="info-box-icon"><i class="fas fa-balance-scale"></i></span>
                        <div class="info-box-content"><span class="info-box-text">Sisa Anggaran</span><span class="info-box-number"><?= money($totalBudget - $totalActual) ?></span></div></div>
                </div>
            </div>

            <h5><i class="fas fa-table"></i> Budget vs Actual per Akun</h5>
            <table class="table table-bordered table-striped">
                <thead class="thead-light">
                    <tr><th>Akun</th><th class="text-right">Anggaran</th><th class="text-right">Realisasi</th><th class="text-right">Selisih</th><th class="text-right">%</th></tr>
                </thead>
                <tbody>
                <?php foreach ($byAccount as $accId => $data):
                    $accBudget = array_sum($data['months']);
                    $accActual = array_sum($actuals[$accId] ?? []);
                    $diff = $accBudget - $accActual;
                    $pct = $accBudget > 0 ? round($accActual / $accBudget * 100, 1) : 0;
                ?>
                    <tr>
                        <td><?= e($data['info']['code']) ?> - <?= e($data['info']['name']) ?></td>
                        <td class="text-right"><?= money($accBudget) ?></td>
                        <td class="text-right"><?= money($accActual) ?></td>
                        <td class="text-right font-weight-bold <?= $diff >= 0 ? 'text-success' : 'text-danger' ?>"><?= money($diff) ?></td>
                        <td class="text-right">
                            <span class="badge badge-<?= $pct <= 100 ? 'success' : 'danger' ?>"><?= $pct ?>%</span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($byAccount)): ?>
                    <tr><td colspan="5" class="text-center text-muted">Belum ada baris budget. Tambahkan di form bawah.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>

            <h5 class="mt-4"><i class="fas fa-edit"></i> Edit Baris Budget</h5>
            <form method="post">
                <input type="hidden" name="action" value="save_lines">
                <table class="table table-bordered" id="budgetTable">
                    <thead class="thead-light"><tr><th width="50%">Akun</th><th width="15%">Bulan</th><th width="25%">Jumlah</th><th width="10%"></th></tr></thead>
                    <tbody>
                    <?php if (!empty($lines)): ?>
                        <?php foreach ($lines as $l): ?>
                        <tr class="budget-row">
                            <td>
                                <select name="account_id[]" class="form-control" required>
                                    <option value="">- Pilih -</option>
                                    <?php foreach ($accounts as $a): ?>
                                        <option value="<?= $a['id'] ?>" <?= (int)$l['account_id'] === (int)$a['id'] ? 'selected' : '' ?>><?= e($a['code']) ?> - <?= e($a['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <select name="month[]" class="form-control" required>
                                    <?php for ($m = 1; $m <= 12; $m++): ?>
                                        <option value="<?= $m ?>" <?= (int)$l['month'] === $m ? 'selected' : '' ?>><?= date('F', mktime(0,0,0,$m,1)) ?></option>
                                    <?php endfor; ?>
                                </select>
                            </td>
                            <td><input type="number" name="amount[]" class="form-control" min="0" step="any" value="<?= $l['amount'] ?>" required></td>
                            <td><button type="button" class="btn btn-danger btn-sm remove-budget-row"><i class="fas fa-times"></i></button></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr class="budget-row">
                            <td>
                                <select name="account_id[]" class="form-control" required>
                                    <option value="">- Pilih -</option>
                                    <?php foreach ($accounts as $a): ?>
                                        <option value="<?= $a['id'] ?>"><?= e($a['code']) ?> - <?= e($a['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <select name="month[]" class="form-control" required>
                                    <?php for ($m = 1; $m <= 12; $m++): ?>
                                        <option value="<?= $m ?>"><?= date('F', mktime(0,0,0,$m,1)) ?></option>
                                    <?php endfor; ?>
                                </select>
                            </td>
                            <td><input type="number" name="amount[]" class="form-control" min="0" step="any" value="0" required></td>
                            <td><button type="button" class="btn btn-danger btn-sm remove-budget-row"><i class="fas fa-times"></i></button></td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
                <button type="button" class="btn btn-success btn-sm" id="addBudgetRow"><i class="fas fa-plus"></i> Tambah Baris</button>
                <button class="btn btn-primary btn-sm"><i class="fas fa-save"></i> Simpan Budget</button>
            </form>
        </div>
    </div>
    <?php
}

function module_scripts(): void
{
    ?>
<script>
$(function () {
    $('#addBudgetRow').click(function () {
        var row = $('.budget-row:first').clone();
        row.find('select').val('');
        row.find('input').val(0);
        $('#budgetTable tbody').append(row);
    });
    $('#budgetTable').on('click', '.remove-budget-row', function () {
        if ($('.budget-row').length > 1) $(this).closest('tr').remove();
    });
});
</script>
    <?php
}
