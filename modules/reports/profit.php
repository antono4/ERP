<?php
// -----------------------------------------------------
// Modul Report: Laporan Laba Rugi
// -----------------------------------------------------

$pageTitle = 'Laporan Laba Rugi';

function module_handle(): void
{
    // read-only
}

function module_render(): void
{
    $from = $_GET['from'] ?? date('Y-m-01');
    $to = $_GET['to'] ?? date('Y-m-d');

    $accountTotals = Database::all(
        "SELECT a.code, a.name, a.type,
                COALESCE(SUM(l.debit),0) AS total_debit,
                COALESCE(SUM(l.credit),0) AS total_credit
         FROM accounts a
         LEFT JOIN journal_entry_lines l ON l.account_id = a.id
         LEFT JOIN journal_entries j ON j.id = l.journal_id
             AND j.entry_date BETWEEN ? AND ?
         WHERE a.type IN ('REVENUE','EXPENSE')
         GROUP BY a.id ORDER BY a.code",
        [$from, $to]
    );

    $revenues = [];
    $expenses = [];
    $totalRevenue = 0;
    $totalExpense = 0;
    foreach ($accountTotals as $a) {
        if ($a['type'] === 'REVENUE') {
            $amount = $a['total_credit'] - $a['total_debit'];
            $revenues[] = $a + ['amount' => $amount];
            $totalRevenue += $amount;
        } else {
            $amount = $a['total_debit'] - $a['total_credit'];
            $expenses[] = $a + ['amount' => $amount];
            $totalExpense += $amount;
        }
    }
    $profit = $totalRevenue - $totalExpense;
    ?>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-balance-scale"></i> Laporan Laba Rugi</h3>
            <div class="card-tools no-print">
                <button class="btn btn-sm btn-secondary" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
            </div>
        </div>
        <div class="card-body">
            <form method="get" class="form-inline mb-3 no-print">
                <input type="hidden" name="page" value="report_profit">
                <label class="mr-2">Periode</label>
                <input type="date" name="from" class="form-control mr-2" value="<?= e($from) ?>">
                <span class="mr-2">s/d</span>
                <input type="date" name="to" class="form-control mr-2" value="<?= e($to) ?>">
                <button class="btn btn-primary"><i class="fas fa-search"></i> Tampilkan</button>
            </form>
            <h6 class="text-muted">Periode: <?= fdate($from) ?> - <?= fdate($to) ?></h6>

            <div class="row">
                <div class="col-md-6">
                    <h5 class="text-success"><i class="fas fa-arrow-up"></i> PENDAPATAN</h5>
                    <table class="table table-bordered">
                        <tbody>
                        <?php foreach ($revenues as $r): ?>
                            <tr><td><?= e($r['code']) ?> - <?= e($r['name']) ?></td>
                                <td class="text-right"><?= money($r['amount']) ?></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot><tr class="table-success"><td class="font-weight-bold">Total Pendapatan</td>
                            <td class="text-right font-weight-bold"><?= money($totalRevenue) ?></td></tr></tfoot>
                    </table>
                </div>
                <div class="col-md-6">
                    <h5 class="text-danger"><i class="fas fa-arrow-down"></i> BEBAN</h5>
                    <table class="table table-bordered">
                        <tbody>
                        <?php foreach ($expenses as $x): ?>
                            <tr><td><?= e($x['code']) ?> - <?= e($x['name']) ?></td>
                                <td class="text-right"><?= money($x['amount']) ?></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot><tr class="table-danger"><td class="font-weight-bold">Total Beban</td>
                            <td class="text-right font-weight-bold"><?= money($totalExpense) ?></td></tr></tfoot>
                    </table>
                </div>
            </div>

            <div class="row mt-3">
                <div class="col-md-12">
                    <div class="info-box <?= $profit >= 0 ? 'bg-success' : 'bg-danger' ?>">
                        <span class="info-box-icon"><i class="fas fa-<?= $profit >= 0 ? 'smile' : 'frown' ?>"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">LABA (RUGI) BERSIH</span>
                            <span class="info-box-number" style="font-size:1.5rem"><?= money($profit) ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}
