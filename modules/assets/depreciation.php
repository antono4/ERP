<?php
// -----------------------------------------------------
// Modul Assets: Penyusutan (Depreciation)
// -----------------------------------------------------

$pageTitle = 'Penyusutan Aset';

function module_handle(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $action = $_POST['action'] ?? '';

    if ($action === 'calculate') {
        $period = $_POST['period'];
        $assets = Database::all("SELECT * FROM assets WHERE status = 'ACTIVE'");
        $created = 0;
        foreach ($assets as $a) {
            $existing = Database::row('SELECT id FROM asset_depreciations WHERE asset_id = ? AND period = ?', [$a['id'], $period]);
            if ($existing) continue;
            $monthly = ($a['purchase_value'] - $a['salvage_value']) / $a['useful_life_years'] / 12;
            if ($monthly > 0) {
                Database::query('INSERT INTO asset_depreciations (asset_id, period, amount) VALUES (?,?,?)', [$a['id'], $period, round($monthly)]);
                $created++;
            }
        }
        logActivity('assets', 'CALC_DEPRECIATION', "Penyusutan periode {$period}: {$created} aset");
        setFlash('success', "Penyusutan periode {$period} dihitung untuk {$created} aset.");
        redirect('index.php?page=depreciation&period=' . $period);
    }

    if ($action === 'post') {
        $period = $_POST['period'];
        Auth::requireRole(['admin', 'manager']);
        $items = Database::all(
            'SELECT d.*, a.name, a.code FROM asset_depreciations d JOIN assets a ON a.id = d.asset_id WHERE d.period = ? AND d.posted = 0',
            [$period]
        );
        if (empty($items)) {
            setFlash('warning', 'Tidak ada penyusutan yang perlu diposting.');
            redirect('index.php?page=depreciation&period=' . $period);
        }

        Database::begin();
        try {
            $total = array_sum(array_column($items, 'amount'));
            $exp = Database::value("SELECT id FROM accounts WHERE code='5-1300'");
            $acc = Database::value("SELECT id FROM accounts WHERE code='1-1510'");
            if (!$exp || !$acc) {
                throw new Exception('Akun penyusutan belum disetup.');
            }
            $jeNo = generateNumber('journal_entries', 'entry_number', 'JE');
            Database::query(
                'INSERT INTO journal_entries (entry_number, entry_date, description, reference, created_by) VALUES (?,?,?,?,?)',
                [$jeNo, $period . '-28', "Penyusutan aset periode {$period}", "DEPR-{$period}", Auth::user()['id']]
            );
            $jeId = (int)Database::lastId();
            Database::query('INSERT INTO journal_entry_lines (journal_id, account_id, debit, credit) VALUES (?,?,?,0)', [$jeId, $exp, $total]);
            Database::query('INSERT INTO journal_entry_lines (journal_id, account_id, debit, credit) VALUES (?,?,0,?)', [$jeId, $acc, $total]);
            Database::query('UPDATE asset_depreciations SET posted = 1, journal_id = ? WHERE period = ?', [$jeId, $period]);
            Database::commit();
            logActivity('assets', 'POST_DEPRECIATION', "Penyusutan {$period} diposting, jurnal {$jeNo}");
            setFlash('success', "Penyusutan {$period} diposting ke jurnal ({$jeNo}).");
        } catch (Exception $ex) {
            Database::rollback();
            setFlash('danger', 'Gagal: ' . $ex->getMessage());
        }
        redirect('index.php?page=depreciation&period=' . $period);
    }
}

function module_render(): void
{
    $period = $_GET['period'] ?? date('Y-m');
    $items = Database::all(
        'SELECT d.*, a.code, a.name, a.category FROM asset_depreciations d JOIN assets a ON a.id = d.asset_id WHERE d.period = ? ORDER BY a.code',
        [$period]
    );
    $total = array_sum(array_column($items, 'amount'));
    $posted = count(array_filter($items, fn($i) => $i['posted']));
    $canPost = in_array(Auth::user()['role'], ['admin','manager'], true);
    ?>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-calculator"></i> Penyusutan Aset — <?= e($period) ?></h3>
            <div class="card-tools">
                <?php if ($canPost && count($items) > 0 && $posted < count($items)): ?>
                    <form method="post" class="d-inline" onsubmit="return confirm('Posting penyusutan ke jurnal?')">
                        <input type="hidden" name="action" value="post">
                        <input type="hidden" name="period" value="<?= e($period) ?>">
                        <button class="btn btn-success btn-sm"><i class="fas fa-check"></i> Posting ke Jurnal</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body">
            <form method="post" class="form-inline mb-3">
                <input type="hidden" name="action" value="calculate">
                <label class="mr-2">Periode</label>
                <input type="month" name="period" class="form-control mr-2" value="<?= e($period) ?>">
                <button class="btn btn-primary"><i class="fas fa-calculator"></i> Hitung</button>
                <a href="index.php?page=depreciation&period=<?= e($period) ?>" class="btn btn-outline-secondary ml-2">Lihat</a>
            </form>
            <table class="table table-bordered table-striped">
                <thead class="thead-light">
                    <tr><th>Kode</th><th>Aset</th><th>Kategori</th><th class="text-right">Penyusutan Bulanan</th><th>Status</th></tr>
                </thead>
                <tbody>
                <?php foreach ($items as $it): ?>
                    <tr>
                        <td><?= e($it['code']) ?></td>
                        <td><?= e($it['name']) ?></td>
                        <td><?= e($it['category']) ?></td>
                        <td class="text-right"><?= money($it['amount']) ?></td>
                        <td><?= $it['posted'] ? '<span class="badge badge-success">POSTED</span>' : '<span class="badge badge-warning">DRAFT</span>' ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($items)): ?>
                    <tr><td colspan="5" class="text-center text-muted">Belum ada penyusutan untuk periode ini. Klik "Hitung" untuk membuat.</td></tr>
                <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr><td colspan="3" class="text-right font-weight-bold">TOTAL</td><td class="text-right font-weight-bold text-danger"><?= money($total) ?></td><td></td></tr>
                </tfoot>
            </table>
        </div>
    </div>
    <?php
}
