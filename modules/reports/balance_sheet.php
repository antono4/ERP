<?php
// -----------------------------------------------------
// Modul Report: Neraca (Balance Sheet)
// -----------------------------------------------------

$pageTitle = 'Neraca (Balance Sheet)';

function module_handle(): void
{
    // read-only
}

function module_render(): void
{
    $asOf = $_GET['as_of'] ?? date('Y-m-d');

    $accounts = Database::all(
        'SELECT a.*,
            COALESCE(SUM(l.debit),0) AS total_debit,
            COALESCE(SUM(l.credit),0) AS total_credit
         FROM accounts a
         LEFT JOIN journal_entry_lines l ON l.account_id = a.id
         LEFT JOIN journal_entries j ON j.id = l.journal_id AND j.entry_date <= ?
         WHERE a.type IN ("ASSET","LIABILITY","EQUITY")
         GROUP BY a.id ORDER BY a.code',
        [$asOf]
    );

    $assets = [];
    $liabilities = [];
    $equities = [];
    $totalAssets = 0;
    $totalLiabilities = 0;
    $totalEquity = 0;

    foreach ($accounts as $a) {
        if ($a['type'] === 'ASSET') {
            $balance = $a['total_debit'] - $a['total_credit'];
            // Akumulasi penyusutan adalah contra-asset (kredit normal)
            if (strpos($a['code'], '1-1510') !== false) $balance = -$balance;
            $assets[] = $a + ['balance' => $balance];
            $totalAssets += $balance;
        } elseif ($a['type'] === 'LIABILITY') {
            $balance = $a['total_credit'] - $a['total_debit'];
            $liabilities[] = $a + ['balance' => $balance];
            $totalLiabilities += $balance;
        } else {
            $balance = $a['total_credit'] - $a['total_debit'];
            $equities[] = $a + ['balance' => $balance];
            $totalEquity += $balance;
        }
    }

    // Laba berjalan = pendapatan - beban
    $pl = Database::row(
        'SELECT
            COALESCE(SUM(CASE WHEN a.type = "REVENUE" THEN l.credit - l.debit END),0) AS revenue,
            COALESCE(SUM(CASE WHEN a.type = "EXPENSE" THEN l.debit - l.credit END),0) AS expense
         FROM accounts a
         LEFT JOIN journal_entry_lines l ON l.account_id = a.id
         LEFT JOIN journal_entries j ON j.id = l.journal_id AND j.entry_date <= ?
         WHERE a.type IN ("REVENUE","EXPENSE")',
        [$asOf]
    );
    $currentEarnings = $pl['revenue'] - $pl['expense'];
    $totalEquity += $currentEarnings;
    $totalLiabEquity = $totalLiabilities + $totalEquity;
    ?>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-balance-scale"></i> Neraca</h3>
            <div class="card-tools no-print">
                <button class="btn btn-sm btn-secondary" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
            </div>
        </div>
        <div class="card-body">
            <form method="get" class="form-inline mb-3 no-print">
                <input type="hidden" name="page" value="balance_sheet">
                <label class="mr-2">Per Tanggal</label>
                <input type="date" name="as_of" class="form-control mr-2" value="<?= e($asOf) ?>">
                <button class="btn btn-primary"><i class="fas fa-search"></i> Tampilkan</button>
            </form>
            <h6 class="text-muted">Per tanggal: <?= fdate($asOf) ?></h6>

            <div class="row">
                <div class="col-md-6">
                    <h5 class="text-primary"><i class="fas fa-building"></i> ASET</h5>
                    <table class="table table-bordered">
                        <tbody>
                        <?php foreach ($assets as $a): ?>
                            <tr><td><?= e($a['code']) ?> - <?= e($a['name']) ?></td><td class="text-right"><?= money($a['balance']) ?></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot><tr class="table-primary"><td class="font-weight-bold">TOTAL ASET</td><td class="text-right font-weight-bold"><?= money($totalAssets) ?></td></tr></tfoot>
                    </table>
                </div>
                <div class="col-md-6">
                    <h5 class="text-warning"><i class="fas fa-file-invoice"></i> KEWAJIBAN</h5>
                    <table class="table table-bordered">
                        <tbody>
                        <?php foreach ($liabilities as $l): ?>
                            <tr><td><?= e($l['code']) ?> - <?= e($l['name']) ?></td><td class="text-right"><?= money($l['balance']) ?></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot><tr class="table-warning"><td class="font-weight-bold">Total Kewajiban</td><td class="text-right font-weight-bold"><?= money($totalLiabilities) ?></td></tr></tfoot>
                    </table>

                    <h5 class="text-info mt-3"><i class="fas fa-coins"></i> EKUITAS</h5>
                    <table class="table table-bordered">
                        <tbody>
                        <?php foreach ($equities as $q): ?>
                            <tr><td><?= e($q['code']) ?> - <?= e($q['name']) ?></td><td class="text-right"><?= money($q['balance']) ?></td></tr>
                        <?php endforeach; ?>
                            <tr><td>Laba (Rugi) Berjalan</td><td class="text-right"><?= money($currentEarnings) ?></td></tr>
                        </tbody>
                        <tfoot><tr class="table-info"><td class="font-weight-bold">Total Ekuitas</td><td class="text-right font-weight-bold"><?= money($totalEquity) ?></td></tr></tfoot>
                    </table>

                    <table class="table table-bordered mt-3">
                        <tr class="<?= abs($totalAssets - $totalLiabEquity) < 1 ? 'table-success' : 'table-danger' ?>">
                            <td class="font-weight-bold">TOTAL KEWAJIBAN + EKUITAS</td>
                            <td class="text-right font-weight-bold"><?= money($totalLiabEquity) ?></td>
                        </tr>
                    </table>
                    <?php if (abs($totalAssets - $totalLiabEquity) >= 1): ?>
                        <div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> Neraca tidak seimbang. Selisih: <?= money($totalAssets - $totalLiabEquity) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php
}
