<?php
// -----------------------------------------------------
// Modul Finance: Jurnal Umum
// -----------------------------------------------------

$pageTitle = 'Jurnal Umum';

function module_handle(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $entryDate = $_POST['entry_date'];
        $description = trim($_POST['description']);
        $reference = trim($_POST['reference'] ?? '');
        $accountIds = $_POST['account_id'] ?? [];
        $debits = $_POST['debit'] ?? [];
        $credits = $_POST['credit'] ?? [];

        $lines = [];
        $totalDebit = 0;
        $totalCredit = 0;
        foreach ($accountIds as $i => $aid) {
            $aid = (int)$aid;
            $debit = (float)($debits[$i] ?? 0);
            $credit = (float)($credits[$i] ?? 0);
            if ($aid > 0 && ($debit > 0 || $credit > 0)) {
                $lines[] = ['account_id' => $aid, 'debit' => $debit, 'credit' => $credit];
                $totalDebit += $debit;
                $totalCredit += $credit;
            }
        }

        if (count($lines) < 2 || abs($totalDebit - $totalCredit) > 0.001) {
            setFlash('danger', 'Jurnal harus seimbang (total debit = total kredit) dan minimal 2 baris.');
            redirect('index.php?page=journal');
        }

        Database::begin();
        try {
            $jeNo = generateNumber('journal_entries', 'entry_number', 'JE');
            Database::query(
                'INSERT INTO journal_entries (entry_number, entry_date, description, reference, created_by) VALUES (?,?,?,?,?)',
                [$jeNo, $entryDate, $description, $reference, Auth::user()['id']]
            );
            $jeId = (int)Database::lastId();
            foreach ($lines as $l) {
                Database::query(
                    'INSERT INTO journal_entry_lines (journal_id, account_id, debit, credit) VALUES (?,?,?,?)',
                    [$jeId, $l['account_id'], $l['debit'], $l['credit']]
                );
            }
            Database::commit();
            setFlash('success', "Jurnal {$jeNo} berhasil dibuat.");
        } catch (Exception $ex) {
            Database::rollback();
            setFlash('danger', 'Gagal: ' . $ex->getMessage());
        }
        redirect('index.php?page=journal');
    } elseif ($action === 'delete') {
        Auth::requireRole(['admin']);
        $id = (int)$_POST['id'];
        Database::query('DELETE FROM journal_entries WHERE id = ?', [$id]);
        setFlash('success', 'Jurnal berhasil dihapus.');
        redirect('index.php?page=journal');
    }
}

function module_render(): void
{
    $entries = Database::all(
        'SELECT j.*, u.full_name AS creator,
            (SELECT SUM(debit) FROM journal_entry_lines WHERE journal_id = j.id) AS total_debit
         FROM journal_entries j
         LEFT JOIN users u ON u.id = j.created_by
         ORDER BY j.entry_date DESC, j.id DESC'
    );
    $accounts = Database::all('SELECT * FROM accounts ORDER BY code');
    $isAdmin = Auth::user()['role'] === 'admin';
    ?>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-file-invoice"></i> Daftar Jurnal</h3>
            <div class="card-tools">
                <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#journalModal">
                    <i class="fas fa-plus"></i> Jurnal Manual
                </button>
            </div>
        </div>
        <div class="card-body">
            <table class="table table-bordered table-striped datatable">
                <thead><tr><th>No. Jurnal</th><th>Tanggal</th><th>Deskripsi</th><th>Referensi</th><th class="text-right">Total</th><th>Oleh</th><th width="100">Aksi</th></tr></thead>
                <tbody>
                <?php foreach ($entries as $j): ?>
                    <tr>
                        <td><?= e($j['entry_number']) ?></td>
                        <td><?= fdate($j['entry_date']) ?></td>
                        <td><?= e($j['description']) ?></td>
                        <td><?= e($j['reference'] ?? '-') ?></td>
                        <td class="text-right"><?= money($j['total_debit']) ?></td>
                        <td><?= e($j['creator'] ?? '-') ?></td>
                        <td>
                            <button class="btn btn-sm btn-info" data-toggle="modal" data-target="#detailModal<?= $j['id'] ?>"><i class="fas fa-eye"></i></button>
                            <?php if ($isAdmin): ?>
                            <form method="post" class="d-inline" onsubmit="return confirm('Hapus jurnal ini?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $j['id'] ?>">
                                <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php foreach ($entries as $j):
        $lines = Database::all(
            'SELECT l.*, a.code, a.name FROM journal_entry_lines l JOIN accounts a ON a.id = l.account_id WHERE l.journal_id = ?',
            [$j['id']]
        );
    ?>
    <div class="modal fade" id="detailModal<?= $j['id'] ?>">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?= e($j['entry_number']) ?> &mdash; <?= e($j['description']) ?></h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <table class="table table-sm table-bordered">
                        <thead class="thead-light"><tr><th>Kode</th><th>Akun</th><th class="text-right">Debit</th><th class="text-right">Kredit</th></tr></thead>
                        <tbody>
                        <?php foreach ($lines as $l): ?>
                            <tr>
                                <td><?= e($l['code']) ?></td>
                                <td><?= e($l['name']) ?></td>
                                <td class="text-right"><?= $l['debit'] > 0 ? money($l['debit']) : '-' ?></td>
                                <td class="text-right"><?= $l['credit'] > 0 ? money($l['credit']) : '-' ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="2" class="text-right font-weight-bold">TOTAL</td>
                                <td class="text-right font-weight-bold"><?= money(array_sum(array_column($lines, 'debit'))) ?></td>
                                <td class="text-right font-weight-bold"><?= money(array_sum(array_column($lines, 'credit'))) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <div class="modal fade" id="journalModal">
        <div class="modal-dialog modal-xl">
            <form method="post" class="modal-content">
                <input type="hidden" name="action" value="create">
                <div class="modal-header"><h5 class="modal-title">Buat Jurnal Manual</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button></div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group"><label>Tanggal</label><input type="date" name="entry_date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
                        </div>
                        <div class="col-md-5">
                            <div class="form-group"><label>Deskripsi</label><input type="text" name="description" class="form-control" required></div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group"><label>Referensi</label><input type="text" name="reference" class="form-control" placeholder="cth: PAYROLL-0826"></div>
                        </div>
                    </div>
                    <table class="table table-bordered" id="journalTable">
                        <thead class="thead-light">
                            <tr><th width="45%">Akun</th><th width="22%">Debit</th><th width="22%">Kredit</th><th width="5%"></th></tr>
                        </thead>
                        <tbody>
                            <?php for ($i = 0; $i < 2; $i++): ?>
                            <tr class="journal-row">
                                <td>
                                    <select name="account_id[]" class="form-control" required>
                                        <option value="">- Pilih Akun -</option>
                                        <?php foreach ($accounts as $a): ?>
                                            <option value="<?= $a['id'] ?>"><?= e($a['code']) ?> - <?= e($a['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td><input type="number" name="debit[]" class="form-control debit-input" min="0" step="any" value="0"></td>
                                <td><input type="number" name="credit[]" class="form-control credit-input" min="0" step="any" value="0"></td>
                                <td><button type="button" class="btn btn-danger btn-sm remove-row"><i class="fas fa-times"></i></button></td>
                            </tr>
                            <?php endfor; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td class="text-right font-weight-bold">TOTAL</td>
                                <td class="font-weight-bold" id="totalDebit">Rp 0</td>
                                <td class="font-weight-bold" id="totalCredit">Rp 0</td>
                                <td></td>
                            </tr>
                            <tr>
                                <td colspan="4" id="balanceStatus" class="text-center text-muted">Total debit harus sama dengan total kredit</td>
                            </tr>
                        </tfoot>
                    </table>
                    <button type="button" class="btn btn-success btn-sm" id="addJournalRow"><i class="fas fa-plus"></i> Tambah Baris</button>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary"><i class="fas fa-save"></i> Simpan Jurnal</button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                </div>
            </form>
        </div>
    </div>
    <?php
}

function module_scripts(): void
{
    ?>
<script>
function recalcJournal() {
    var td = 0, tc = 0;
    $('.debit-input').each(function () { td += parseFloat($(this).val()) || 0; });
    $('.credit-input').each(function () { tc += parseFloat($(this).val()) || 0; });
    $('#totalDebit').text('Rp ' + td.toLocaleString('id-ID'));
    $('#totalCredit').text('Rp ' + tc.toLocaleString('id-ID'));
    var ok = td === tc && td > 0;
    $('#balanceStatus')
        .removeClass('text-muted text-danger text-success')
        .addClass(ok ? 'text-success' : 'text-danger')
        .text(ok ? 'Seimbang (Balanced)' : 'Tidak seimbang: selisih Rp ' + Math.abs(td - tc).toLocaleString('id-ID'));
}
$(function () {
    $('#journalTable').on('input', '.debit-input, .credit-input', recalcJournal);
    $('#addJournalRow').click(function () {
        var row = $('#journalTable tbody tr:first').clone();
        row.find('input').val(0);
        row.find('select').val('');
        $('#journalTable tbody').append(row);
    });
    $('#journalTable').on('click', '.remove-row', function () {
        if ($('#journalTable tbody tr').length > 2) {
            $(this).closest('tr').remove();
            recalcJournal();
        }
    });
});
</script>
    <?php
}
