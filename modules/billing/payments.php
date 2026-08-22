<?php
// -----------------------------------------------------
// Modul Billing: Pembayaran (AR Receive / AP Pay)
// -----------------------------------------------------

$pageTitle = 'Pembayaran';

function module_handle(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $action = $_POST['action'] ?? '';

    if ($action === 'pay') {
        $invoiceType = $_POST['invoice_type'];
        $invoiceId = (int)$_POST['invoice_id'];
        $paymentDate = $_POST['payment_date'];
        $amount = (float)$_POST['amount'];
        $method = trim($_POST['payment_method'] ?? 'Transfer Bank');
        $notes = trim($_POST['notes'] ?? '');

        $table = $invoiceType === 'SALES' ? 'sales_invoices' : 'purchase_invoices';
        $inv = Database::row("SELECT * FROM {$table} WHERE id = ?", [$invoiceId]);
        if (!$inv) {
            setFlash('danger', 'Invoice tidak ditemukan.');
            redirect('index.php?page=payments');
        }
        $remaining = $inv['total'] - $inv['paid'];
        if ($amount <= 0 || $amount > $remaining) {
            setFlash('danger', "Jumlah pembayaran tidak valid (sisa: " . money($remaining) . ").");
            redirect('index.php?page=payments&invoice_type=' . $invoiceType . '&invoice_id=' . $invoiceId);
        }

        Database::begin();
        try {
            $paymentType = $invoiceType === 'SALES' ? 'RECEIVE' : 'PAY';
            $payNo = generateNumber('payments', 'payment_number', $paymentType === 'RECEIVE' ? 'RCV' : 'PAY');

            Database::query(
                'INSERT INTO payments (payment_number, payment_type, invoice_type, invoice_id, payment_date, amount, payment_method, notes, created_by) VALUES (?,?,?,?,?,?,?,?,?)',
                [$payNo, $paymentType, $invoiceType, $invoiceId, $paymentDate, $amount, $method, $notes, Auth::user()['id']]
            );

            $newPaid = $inv['paid'] + $amount;
            Database::query("UPDATE {$table} SET paid = ? WHERE id = ?", [$newPaid, $invoiceId]);

            // Auto jurnal pembayaran
            $cash = Database::value("SELECT id FROM accounts WHERE code='1-1000'");
            $bank = Database::value("SELECT id FROM accounts WHERE code='1-1100'");
            $cashAccount = $bank ?: $cash;

            if ($invoiceType === 'SALES') {
                $ar = Database::value("SELECT id FROM accounts WHERE code='1-1200'");
                if ($cashAccount && $ar) {
                    $jeNo = generateNumber('journal_entries', 'entry_number', 'JE');
                    Database::query(
                        'INSERT INTO journal_entries (entry_number, entry_date, description, reference, created_by) VALUES (?,?,?,?,?)',
                        [$jeNo, $paymentDate, "Penerimaan pembayaran {$inv['invoice_number']}", $payNo, Auth::user()['id']]
                    );
                    $jeId = (int)Database::lastId();
                    Database::query('INSERT INTO journal_entry_lines (journal_id, account_id, debit, credit) VALUES (?,?,?,0)', [$jeId, $cashAccount, $amount]);
                    Database::query('INSERT INTO journal_entry_lines (journal_id, account_id, debit, credit) VALUES (?,?,0,?)', [$jeId, $ar, $amount]);
                }
            } else {
                $ap = Database::value("SELECT id FROM accounts WHERE code='2-1000'");
                if ($cashAccount && $ap) {
                    $jeNo = generateNumber('journal_entries', 'entry_number', 'JE');
                    Database::query(
                        'INSERT INTO journal_entries (entry_number, entry_date, description, reference, created_by) VALUES (?,?,?,?,?)',
                        [$jeNo, $paymentDate, "Pembayaran {$inv['invoice_number']}", $payNo, Auth::user()['id']]
                    );
                    $jeId = (int)Database::lastId();
                    Database::query('INSERT INTO journal_entry_lines (journal_id, account_id, debit, credit) VALUES (?,?,?,0)', [$jeId, $ap, $amount]);
                    Database::query('INSERT INTO journal_entry_lines (journal_id, account_id, debit, credit) VALUES (?,?,0,?)', [$jeId, $cashAccount, $amount]);
                }
            }

            autoInvoiceStatus($table, $invoiceId);
            Database::commit();
            logActivity('billing', 'PAYMENT', "{$payNo}: " . money($amount) . " untuk {$inv['invoice_number']}");
            setFlash('success', "Pembayaran {$payNo} berhasil dicatat.");
        } catch (Exception $ex) {
            Database::rollback();
            setFlash('danger', 'Gagal mencatat pembayaran: ' . $ex->getMessage());
        }
        redirect('index.php?page=payments&invoice_type=' . $invoiceType . '&invoice_id=' . $invoiceId);
    }
}

function module_render(): void
{
    $invoiceType = $_GET['invoice_type'] ?? 'SALES';
    $invoiceId = (int)($_GET['invoice_id'] ?? 0);
    $table = $invoiceType === 'SALES' ? 'sales_invoices' : 'purchase_invoices';
    $inv = $invoiceId > 0 ? Database::row("SELECT * FROM {$table} WHERE id = ?", [$invoiceId]) : null;

    $payments = Database::all(
        'SELECT p.*, u.full_name AS creator FROM payments p LEFT JOIN users u ON u.id = p.created_by ORDER BY p.payment_date DESC, p.id DESC LIMIT 200'
    );
    ?>
    <div class="row">
        <div class="col-md-4">
            <div class="card card-primary">
                <div class="card-header"><h3 class="card-title">Form Pembayaran</h3></div>
                <form method="post">
                    <input type="hidden" name="action" value="pay">
                    <input type="hidden" name="invoice_type" value="<?= e($invoiceType) ?>">
                    <div class="card-body">
                        <div class="form-group">
                            <label>Tipe</label>
                            <div class="btn-group btn-group-toggle d-flex" data-toggle="buttons">
                                <a href="index.php?page=payments&invoice_type=SALES" class="btn <?= $invoiceType === 'SALES' ? 'btn-primary' : 'btn-outline-primary' ?> w-50">Piutang (AR)</a>
                                <a href="index.php?page=payments&invoice_type=PURCHASE" class="btn <?= $invoiceType === 'PURCHASE' ? 'btn-primary' : 'btn-outline-primary' ?> w-50">Hutang (AP)</a>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Invoice</label>
                            <select name="invoice_id" class="form-control select2" id="invoiceSelect" required>
                                <option value="">- Pilih Invoice -</option>
                                <?php
                                $openInvoices = Database::all(
                                    "SELECT i.*, " . ($invoiceType === 'SALES' ? "c.name AS partner" : "s.name AS partner") .
                                    " FROM {$table} i JOIN " . ($invoiceType === 'SALES' ? 'customers c ON c.id = i.customer_id' : 'suppliers s ON s.id = i.supplier_id') .
                                    " WHERE i.status IN ('UNPAID','PARTIAL','OVERDUE') ORDER BY i.invoice_date DESC"
                                );
                                foreach ($openInvoices as $oi):
                                ?>
                                    <option value="<?= $oi['id'] ?>" data-total="<?= $oi['total'] ?>" data-paid="<?= $oi['paid'] ?>"
                                        <?= $invoiceId === (int)$oi['id'] ? 'selected' : '' ?>>
                                        <?= e($oi['invoice_number']) ?> - <?= e($oi['partner']) ?> (Sisa: <?= money($oi['total'] - $oi['paid']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Tanggal Bayar</label>
                            <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Jumlah</label>
                            <input type="number" name="amount" id="amountInput" class="form-control" min="0.01" step="any" required>
                            <small class="text-muted" id="remainingInfo"></small>
                        </div>
                        <div class="form-group">
                            <label>Metode</label>
                            <select name="payment_method" class="form-control">
                                <option>Transfer Bank</option>
                                <option>Tunai / Cash</option>
                                <option>Cek / Giro</option>
                                <option>Kartu Kredit</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Catatan</label>
                            <input type="text" name="notes" class="form-control" placeholder="cth: pembayaran termin 1">
                        </div>
                    </div>
                    <div class="card-footer">
                        <button class="btn btn-success btn-block"><i class="fas fa-money-bill-wave"></i> Catat Pembayaran</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Riwayat Pembayaran</h3></div>
                <div class="card-body">
                    <table class="table table-bordered table-striped datatable">
                        <thead>
                            <tr><th>No. Bayar</th><th>Tanggal</th><th>Tipe</th><th>Invoice</th><th class="text-right">Jumlah</th><th>Metode</th><th>Oleh</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($payments as $p): ?>
                            <tr>
                                <td><?= e($p['payment_number']) ?></td>
                                <td><?= fdate($p['payment_date']) ?></td>
                                <td>
                                    <span class="badge badge-<?= $p['payment_type'] === 'RECEIVE' ? 'success' : 'warning' ?>">
                                        <?= $p['payment_type'] === 'RECEIVE' ? 'Terima' : 'Bayar' ?>
                                    </span>
                                </td>
                                <td><?= e($p['invoice_type']) ?> #<?= $p['invoice_id'] ?></td>
                                <td class="text-right"><?= money($p['amount']) ?></td>
                                <td><?= e($p['payment_method']) ?></td>
                                <td><?= e($p['creator'] ?? '-') ?></td>
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

function module_scripts(): void
{
    ?>
<script>
$(function () {
    function updateRemaining() {
        var selected = $('#invoiceSelect').find(':selected');
        var total = parseFloat(selected.data('total')) || 0;
        var paid = parseFloat(selected.data('paid')) || 0;
        var remaining = total - paid;
        if (remaining > 0) {
            $('#amountInput').attr('max', remaining).val(remaining);
            $('#remainingInfo').text('Sisa tagihan: Rp ' + remaining.toLocaleString('id-ID'));
        } else {
            $('#remainingInfo').text('');
        }
    }
    $('#invoiceSelect').on('change', updateRemaining);
    updateRemaining();
});
</script>
    <?php
}
