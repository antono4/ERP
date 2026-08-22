<?php
// -----------------------------------------------------
// Modul POS: Point of Sale (Kasir)
// -----------------------------------------------------

$pageTitle = 'Point of Sale (Kasir)';

function module_handle(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $action = $_POST['action'] ?? '';

    if ($action === 'open_session') {
        $openingCash = (float)$_POST['opening_cash'];
        $existing = Database::row("SELECT * FROM pos_sessions WHERE status = 'OPEN' AND opened_by = ?", [Auth::user()['id']]);
        if ($existing) {
            setFlash('warning', 'Sesi kasir sudah terbuka.');
            redirect('index.php?page=pos');
        }
        $sNo = generateNumber('pos_sessions', 'session_number', 'POS');
        Database::query(
            'INSERT INTO pos_sessions (session_number, opened_by, opening_cash, status) VALUES (?,?,?,\'OPEN\')',
            [$sNo, Auth::user()['id'], $openingCash]
        );
        logActivity('pos', 'OPEN_SESSION', "Sesi kasir {$sNo} dibuka, modal " . money($openingCash));
        setFlash('success', "Sesi kasir {$sNo} dibuka.");
        redirect('index.php?page=pos');
    }

    if ($action === 'close_session') {
        $sessionId = (int)$_POST['session_id'];
        $closingCash = (float)$_POST['closing_cash'];
        Database::query(
            "UPDATE pos_sessions SET closing_cash=?, closed_at=NOW(), status='CLOSED' WHERE id=? AND status='OPEN'",
            [$closingCash, $sessionId]
        );
        logActivity('pos', 'CLOSE_SESSION', "Sesi kasir ditutup, saldo " . money($closingCash));
        setFlash('success', 'Sesi kasir ditutup.');
        redirect('index.php?page=pos');
    }

    if ($action === 'sell') {
        $sessionId = (int)$_POST['session_id'];
        $productIds = $_POST['product_id'] ?? [];
        $qtys = $_POST['qty'] ?? [];
        $prices = $_POST['price'] ?? [];
        $paid = (float)$_POST['paid'];
        $method = trim($_POST['payment_method'] ?? 'CASH');
        $customerId = (int)$_POST['customer_id'] ?: null;

        $validItems = [];
        $total = 0;
        foreach ($productIds as $i => $pid) {
            $pid = (int)$pid;
            $qty = (float)($qtys[$i] ?? 0);
            $price = (float)($prices[$i] ?? 0);
            if ($pid > 0 && $qty > 0) {
                $stock = Database::value('SELECT stock FROM products WHERE id = ?', [$pid]);
                if ($stock < $qty) {
                    $pName = Database::value('SELECT name FROM products WHERE id = ?', [$pid]);
                    setFlash('danger', "Stok {$pName} tidak cukup (sisa {$stock}).");
                    redirect('index.php?page=pos');
                }
                $subtotal = $qty * $price;
                $validItems[] = ['product_id' => $pid, 'qty' => $qty, 'price' => $price, 'subtotal' => $subtotal];
                $total += $subtotal;
            }
        }

        if (empty($validItems) || $paid < $total) {
            setFlash('danger', 'Pembayaran tidak mencukupi atau item kosong.');
            redirect('index.php?page=pos');
        }

        Database::begin();
        try {
            $trxNo = generateNumber('pos_transactions', 'trx_number', 'POS');
            $change = $paid - $total;
            Database::query(
                'INSERT INTO pos_transactions (trx_number, session_id, customer_id, total, paid, change_amount, payment_method, created_by) VALUES (?,?,?,?,?,?,?,?)',
                [$trxNo, $sessionId, $customerId, $total, $paid, $change, $method, Auth::user()['id']]
            );
            $trxId = (int)Database::lastId();
            foreach ($validItems as $it) {
                Database::query(
                    'INSERT INTO pos_transaction_items (trx_id, product_id, qty, price, subtotal) VALUES (?,?,?,?,?)',
                    [$trxId, $it['product_id'], $it['qty'], $it['price'], $it['subtotal']]
                );
                Database::query('UPDATE products SET stock = stock - ? WHERE id = ?', [$it['qty'], $it['product_id']]);
                Database::query(
                    "INSERT INTO stock_movements (product_id, movement_type, qty, reference_type, reference_id, notes, created_by)
                     VALUES (?, 'OUT', ?, 'SALES', ?, ?, ?)",
                    [$it['product_id'], $it['qty'], $trxId, "POS {$trxNo}", Auth::user()['id']]
                );
            }
            // Jurnal: Kas (D) / Pendapatan (K)
            $cash = Database::value("SELECT id FROM accounts WHERE code='1-1000'");
            $rev = Database::value("SELECT id FROM accounts WHERE code='4-1000'");
            if ($cash && $rev) {
                $jeNo = generateNumber('journal_entries', 'entry_number', 'JE');
                Database::query(
                    'INSERT INTO journal_entries (entry_number, entry_date, description, reference, created_by) VALUES (?,?,?,?,?)',
                    [$jeNo, date('Y-m-d'), "Penjualan kasir {$trxNo}", $trxNo, Auth::user()['id']]
                );
                $jeId = (int)Database::lastId();
                Database::query('INSERT INTO journal_entry_lines (journal_id, account_id, debit, credit) VALUES (?,?,?,0)', [$jeId, $cash, $total]);
                Database::query('INSERT INTO journal_entry_lines (journal_id, account_id, debit, credit) VALUES (?,?,0,?)', [$jeId, $rev, $total]);
            }
            Database::commit();
            logActivity('pos', 'SALE', "Transaksi {$trxNo}: " . money($total));
            setFlash('success', "Transaksi {$trxNo} berhasil. Kembalian: " . money($change));
        } catch (Exception $ex) {
            Database::rollback();
            setFlash('danger', 'Gagal: ' . $ex->getMessage());
        }
        redirect('index.php?page=pos');
    }
}

function module_render(): void
{
    $session = Database::row(
        "SELECT s.*, u.full_name FROM pos_sessions s JOIN users u ON u.id = s.opened_by WHERE s.status = 'OPEN' AND s.opened_by = ?",
        [Auth::user()['id']]
    );
    $products = Database::all("SELECT * FROM products WHERE status = 1 AND stock > 0 ORDER BY name");
    $customers = Database::all('SELECT * FROM customers ORDER BY name');

    if (!$session) {
        ?>
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="card card-primary">
                    <div class="card-header text-center"><h4 class="card-title"><i class="fas fa-cash-register"></i> Buka Sesi Kasir</h4></div>
                    <form method="post">
                        <input type="hidden" name="action" value="open_session">
                        <div class="card-body">
                            <div class="form-group">
                                <label>Modal Awal (Kas)</label>
                                <input type="number" name="opening_cash" class="form-control form-control-lg text-right" min="0" step="any" value="500000" required>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button class="btn btn-primary btn-lg btn-block"><i class="fas fa-unlock"></i> Buka Sesi</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php
        return;
    }

    // Sesi terbuka: tampilkan layar kasir
    $todayTrx = Database::all(
        'SELECT * FROM pos_transactions WHERE session_id = ? AND DATE(trx_date) = CURDATE() ORDER BY trx_date DESC',
        [$session['id']]
    );
    $todayTotal = array_sum(array_column($todayTrx, 'total'));
    ?>
    <div class="row">
        <div class="col-md-8">
            <div class="card card-success">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-cash-register"></i> Sesi: <?= e($session['session_number']) ?> | Modal: <?= money($session['opening_cash']) ?></h3>
                    <div class="card-tools">
                        <button class="btn btn-danger btn-sm" data-toggle="modal" data-target="#closeSessionModal"><i class="fas fa-lock"></i> Tutup Sesi</button>
                    </div>
                </div>
                <div class="card-body">
                    <form method="post" id="posForm">
                        <input type="hidden" name="action" value="sell">
                        <input type="hidden" name="session_id" value="<?= $session['id'] ?>">
                        <div class="form-group">
                            <label>Customer (opsional)</label>
                            <select name="customer_id" class="form-control select2">
                                <option value="">- Umum / Walk-in -</option>
                                <?php foreach ($customers as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <table class="table table-bordered" id="posTable">
                            <thead class="thead-light">
                                <tr><th width="50%">Produk</th><th width="15%">Qty</th><th width="20%">Harga</th><th width="15%" class="text-right">Subtotal</th><th width="5%"></th></tr>
                            </thead>
                            <tbody>
                                <tr class="pos-row">
                                    <td>
                                        <select name="product_id[]" class="form-control pos-product" required>
                                            <option value="">- Pilih -</option>
                                            <?php foreach ($products as $p): ?>
                                                <option value="<?= $p['id'] ?>" data-price="<?= $p['selling_price'] ?>" data-stock="<?= $p['stock'] ?>">
                                                    <?= e($p['code']) ?> - <?= e($p['name']) ?> (Stok: <?= number_format($p['stock']) ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td><input type="number" name="qty[]" class="form-control pos-qty" min="1" step="any" value="1" required></td>
                                    <td><input type="number" name="price[]" class="form-control pos-price" min="0" step="any" value="0" required></td>
                                    <td class="text-right pos-subtotal">Rp 0</td>
                                    <td><button type="button" class="btn btn-danger btn-sm remove-pos-row"><i class="fas fa-times"></i></button></td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr><td colspan="3" class="text-right font-weight-bold">TOTAL</td><td class="text-right font-weight-bold" id="posTotal">Rp 0</td><td></td></tr>
                            </tfoot>
                        </table>
                        <button type="button" class="btn btn-success btn-sm" id="addPosRow"><i class="fas fa-plus"></i> Tambah Baris</button>

                        <div class="row mt-3">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Metode Bayar</label>
                                    <select name="payment_method" class="form-control">
                                        <option value="CASH">Tunai</option>
                                        <option value="DEBIT">Kartu Debit</option>
                                        <option value="CREDIT">Kartu Kredit</option>
                                        <option value="TRANSFER">Transfer</option>
                                        <option value="QRIS">QRIS</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Jumlah Dibayar</label>
                                    <input type="number" name="paid" id="posPaid" class="form-control form-control-lg text-right" min="0" step="any" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Kembalian</label>
                                    <input type="text" id="posChange" class="form-control form-control-lg text-right bg-light" readonly value="Rp 0">
                                </div>
                            </div>
                        </div>
                        <button class="btn btn-primary btn-lg btn-block mt-2" id="posSubmit" disabled><i class="fas fa-money-bill-wave"></i> Proses Pembayaran</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-receipt"></i> Transaksi Hari Ini</h3>
                </div>
                <div class="card-body p-0" style="max-height:600px;overflow-y:auto">
                    <table class="table table-sm">
                        <thead><tr><th>No.</th><th>Waktu</th><th class="text-right">Total</th></tr></thead>
                        <tbody>
                        <?php foreach ($todayTrx as $t): ?>
                            <tr>
                                <td><?= e($t['trx_number']) ?></td>
                                <td><?= date('H:i', strtotime($t['trx_date'])) ?></td>
                                <td class="text-right"><?= money($t['total']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-success">
                                <td colspan="2" class="text-right font-weight-bold">TOTAL HARI INI</td>
                                <td class="text-right font-weight-bold"><?= money($todayTotal) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="closeSessionModal">
        <div class="modal-dialog">
            <form method="post" class="modal-content">
                <input type="hidden" name="action" value="close_session">
                <input type="hidden" name="session_id" value="<?= $session['id'] ?>">
                <div class="modal-header"><h5 class="modal-title">Tutup Sesi Kasir</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
                <div class="modal-body">
                    <p>Modal awal: <b><?= money($session['opening_cash']) ?></b></p>
                    <p>Penjualan hari ini: <b><?= money($todayTotal) ?></b></p>
                    <p>Seharusnya kas: <b><?= money($session['opening_cash'] + $todayTotal) ?></b></p>
                    <div class="form-group">
                        <label>Jumlah Kas Aktual (dihitung)</label>
                        <input type="number" name="closing_cash" class="form-control form-control-lg text-right" min="0" step="any" value="<?= $session['opening_cash'] + $todayTotal ?>" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-danger"><i class="fas fa-lock"></i> Tutup Sesi</button>
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
function recalcPos() {
    var total = 0;
    $('.pos-row').each(function () {
        var qty = parseFloat($(this).find('.pos-qty').val()) || 0;
        var price = parseFloat($(this).find('.pos-price').val()) || 0;
        var sub = qty * price;
        total += sub;
        $(this).find('.pos-subtotal').text('Rp ' + sub.toLocaleString('id-ID'));
    });
    $('#posTotal').text('Rp ' + total.toLocaleString('id-ID'));
    var paid = parseFloat($('#posPaid').val()) || 0;
    var change = paid - total;
    $('#posChange').val('Rp ' + Math.max(0, change).toLocaleString('id-ID'));
    $('#posSubmit').prop('disabled', total <= 0 || paid < total);
}
$(function () {
    $('#posTable').on('change', '.pos-product', function () {
        var price = $(this).find(':selected').data('price') || 0;
        $(this).closest('tr').find('.pos-price').val(price);
        recalcPos();
    });
    $('#posTable').on('input', '.pos-qty, .pos-price', recalcPos);
    $('#posPaid').on('input', recalcPos);
    $('#addPosRow').click(function () {
        var row = $('.pos-row:first').clone();
        row.find('select').val('');
        row.find('.pos-qty').val(1);
        row.find('.pos-price').val(0);
        row.find('.pos-subtotal').text('Rp 0');
        $('#posTable tbody').append(row);
    });
    $('#posTable').on('click', '.remove-pos-row', function () {
        if ($('.pos-row').length > 1) {
            $(this).closest('tr').remove();
            recalcPos();
        }
    });
});
</script>
    <?php
}
