<?php
// -----------------------------------------------------
// Modul Finance: Buku Besar (General Ledger)
// -----------------------------------------------------

$pageTitle = 'Buku Besar (General Ledger)';

function module_handle(): void
{
    // read-only
}

function module_render(): void
{
    $accountId = (int)($_GET['account_id'] ?? 0);
    $from = $_GET['from'] ?? date('Y-m-01');
    $to = $_GET['to'] ?? date('Y-m-d');
    $accounts = Database::all('SELECT * FROM accounts ORDER BY code');

    $entries = [];
    $account = null;
    $openingBalance = 0;
    if ($accountId > 0) {
        $account = Database::row('SELECT * FROM accounts WHERE id = ?', [$accountId]);
        // Saldo awal: semua transaksi sebelum periode
        $isDebitNormal = in_array($account['type'], ['ASSET','EXPENSE'], true);
        $opening = Database::row(
            'SELECT COALESCE(SUM(l.debit),0) AS d, COALESCE(SUM(l.credit),0) AS c
             FROM journal_entry_lines l JOIN journal_entries j ON j.id = l.journal_id
             WHERE l.account_id = ? AND j.entry_date < ?',
            [$accountId, $from]
        );
        $openingBalance = $isDebitNormal ? $opening['d'] - $opening['c'] : $opening['c'] - $opening['d'];

        $entries = Database::all(
            'SELECT j.entry_date, j.entry_number, j.description, j.reference, l.debit, l.credit
             FROM journal_entry_lines l JOIN journal_entries j ON j.id = l.journal_id
             WHERE l.account_id = ? AND j.entry_date BETWEEN ? AND ?
             ORDER BY j.entry_date, j.id',
            [$accountId, $from, $to]
        );
    }
    ?>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-book-open"></i> Buku Besar</h3>
            <div class="card-tools no-print">
                <button class="btn btn-sm btn-secondary" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
            </div>
        </div>
        <div class="card-body">
            <form method="get" class="form-inline mb-3 no-print">
                <input type="hidden" name="page" value="general_ledger">
                <select name="account_id" class="form-control select2 mr-2" style="min-width:300px" required>
                    <option value="">- Pilih Akun -</option>
                    <?php foreach ($accounts as $a): ?>
                        <option value="<?= $a['id'] ?>" <?= $accountId === (int)$a['id'] ? 'selected' : '' ?>><?= e($a['code']) ?> - <?= e($a['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="date" name="from" class="form-control mr-2" value="<?= e($from) ?>">
                <span class="mr-2">s/d</span>
                <input type="date" name="to" class="form-control mr-2" value="<?= e($to) ?>">
                <button class="btn btn-primary"><i class="fas fa-search"></i> Tampilkan</button>
            </form>

            <?php if ($account): ?>
            <h5><?= e($account['code']) ?> - <?= e($account['name']) ?> <small class="text-muted">(<?= e($account['type']) ?>)</small></h5>
            <h6 class="text-muted">Periode: <?= fdate($from) ?> - <?= fdate($to) ?></h6>
            <table class="table table-bordered table-striped">
                <thead class="thead-light">
                    <tr><th>Tanggal</th><th>No. Jurnal</th><th>Deskripsi</th><th>Referensi</th><th class="text-right">Debit</th><th class="text-right">Kredit</th><th class="text-right">Saldo</th></tr>
                </thead>
                <tbody>
                    <tr class="table-secondary">
                        <td colspan="6" class="font-weight-bold">Saldo Awal</td>
                        <td class="text-right font-weight-bold"><?= money($openingBalance) ?></td>
                    </tr>
                    <?php
                    $balance = $openingBalance;
                    $isDebitNormal = in_array($account['type'], ['ASSET','EXPENSE'], true);
                    foreach ($entries as $en):
                        $balance += $isDebitNormal ? ($en['debit'] - $en['credit']) : ($en['credit'] - $en['debit']);
                    ?>
                        <tr>
                            <td><?= fdate($en['entry_date']) ?></td>
                            <td><?= e($en['entry_number']) ?></td>
                            <td><?= e($en['description']) ?></td>
                            <td><?= e($en['reference'] ?? '-') ?></td>
                            <td class="text-right"><?= $en['debit'] > 0 ? money($en['debit']) : '-' ?></td>
                            <td class="text-right"><?= $en['credit'] > 0 ? money($en['credit']) : '-' ?></td>
                            <td class="text-right"><?= money($balance) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="table-primary">
                        <td colspan="6" class="text-right font-weight-bold">SALDO AKHIR</td>
                        <td class="text-right font-weight-bold"><?= money($balance) ?></td>
                    </tr>
                </tfoot>
            </table>
            <?php else: ?>
                <p class="text-muted">Pilih akun untuk melihat buku besarnya.</p>
            <?php endif; ?>
        </div>
    </div>
    <?php
}
