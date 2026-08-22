<?php
// -----------------------------------------------------
// Modul Returns: Retur Penjualan
// -----------------------------------------------------

$pageTitle = 'Retur Penjualan';

function module_handle(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $soId = (int)$_POST['so_id'];
        $so = Database::row('SELECT * FROM sales_orders WHERE id = ?', [$soId]);
        if (!$so || $so['status'] !== 'DELIVERED') {
            setFlash('danger', 'Hanya SO yang sudah DELIVERED yang bisa diretur.');
            redirect('index.php?page=sales_return');
        }
        redirect('index.php?page=sales_return&create=' . $soId);
    }

    if ($action === 'save') {
        $soId = (int)$_POST['so_id'];
        $returnDate = $_POST['return_date'];
        $reason = trim($_POST['reason'] ?? '');
        $qtys = $_POST['qty'] ?? [];
        $prices = $_POST['price'] ?? [];

        $so = Database::row('SELECT * FROM sales_orders WHERE id = ?', [$soId]);
        if (!$so) redirect('index.php?page=sales_return');

        $validItems = [];
        $total = 0;
        foreach ($qtys as $itemId => $qty) {
            $qty = (float)$qty;
            if ($qty <= 0) continue;
            $item = Database::row('SELECT i.*, p.name FROM sales_order_items i JOIN products p ON p.id = i.product_id WHERE i.id = ?', [(int)$itemId]);
            if (!$item) continue;
            $subtotal = $qty * (float)$prices[$itemId];
            $validItems[] = ['product_id' => $item['product_id'], 'qty' => $qty, 'price' => (float)$prices[$itemId], 'subtotal' => $subtotal, 'name' => $item['name']];
            $total += $subtotal;
        }

        if (empty($validItems)) {
            setFlash('danger', 'Minimal satu item harus diisi.');
            redirect('index.php?page=sales_return&create=' . $soId);
        }

        Database::begin();
        try {
            $retNo = generateNumber('sales_returns', 'return_number', 'SR');
            Database::query(
                'INSERT INTO sales_returns (return_number, so_id, return_date, total, reason, status, created_by) VALUES (?,?,?,?,?,\'DRAFT\',?)',
                [$retNo, $soId, $returnDate, $total, $reason, Auth::user()['id']]
            );
            $retId = (int)Database::lastId();
            foreach ($validItems as $it) {
                Database::query(
                    'INSERT INTO sales_return_items (return_id, product_id, qty, price, subtotal) VALUES (?,?,?,?,?)',
                    [$retId, $it['product_id'], $it['qty'], $it['price'], $it['subtotal']]
                );
            }
            Database::commit();
            logActivity('returns', 'CREATE_RETURN', "Retur penjualan {$retNo} untuk SO {$so['so_number']}");
            setFlash('success', "Retur {$retNo} dibuat. Konfirmasi untuk memproses stok & jurnal.");
        } catch (Exception $ex) {
            Database::rollback();
            setFlash('danger', 'Gagal: ' . $ex->getMessage());
        }
        redirect('index.php?page=sales_return');
    }

    if ($action === 'confirm') {
        $retId = (int)$_POST['id'];
        $ret = Database::row('SELECT * FROM sales_returns WHERE id = ?', [$retId]);
        if (!$ret || $ret['status'] !== 'DRAFT') redirect('index.php?page=sales_return');

        $items = Database::all('SELECT * FROM sales_return_items WHERE return_id = ?', [$retId]);
        Database::begin();
        try {
            $totalCogs = 0;
            foreach ($items as $it) {
                Database::query('UPDATE products SET stock = stock + ? WHERE id = ?', [$it['qty'], $it['product_id']]);
                Database::query(
                    "INSERT INTO stock_movements (product_id, movement_type, qty, reference_type, reference_id, notes, created_by)
                     VALUES (?, 'IN', ?, 'SALES', ?, ?, ?)",
                    [$it['product_id'], $it['qty'], $retId, "Retur {$ret['return_number']}", Auth::user()['id']]
                );
                $prod = Database::row('SELECT purchase_price FROM products WHERE id = ?', [$it['product_id']]);
                $totalCogs += $it['qty'] * $prod['purchase_price'];
            }
            Database::query("UPDATE sales_returns SET status='CONFIRMED' WHERE id=?", [$retId]);

            // Jurnal retur: pendapatan (D) / piutang (K), persediaan (D) / HPP (K)
            $rev = Database::value("SELECT id FROM accounts WHERE code='4-1000'");
            $ar  = Database::value("SELECT id FROM accounts WHERE code='1-1200'");
            $inv = Database::value("SELECT id FROM accounts WHERE code='1-1300'");
            $cogs = Database::value("SELECT id FROM accounts WHERE code='5-1000'");
            if ($rev && $ar && $inv && $cogs) {
                $jeNo = generateNumber('journal_entries', 'entry_number', 'JE');
                Database::query(
                    'INSERT INTO journal_entries (entry_number, entry_date, description, reference, created_by) VALUES (?,?,?,?,?)',
                    [$jeNo, $ret['return_date'], "Retur penjualan {$ret['return_number']}", $ret['return_number'], Auth::user()['id']]
                );
                $jeId = (int)Database::lastId();
                Database::query('INSERT INTO journal_entry_lines (journal_id, account_id, debit, credit) VALUES (?,?,?,0)', [$jeId, $rev, $ret['total']]);
                Database::query('INSERT INTO journal_entry_lines (journal_id, account_id, debit, credit) VALUES (?,?,0,?)', [$jeId, $ar, $ret['total']]);
                if ($totalCogs > 0) {
                    Database::query('INSERT INTO journal_entry_lines (journal_id, account_id, debit, credit) VALUES (?,?,?,0)', [$jeId, $inv, $totalCogs]);
                    Database::query('INSERT INTO journal_entry_lines (journal_id, account_id, debit, credit) VALUES (?,?,0,?)', [$jeId, $cogs, $totalCogs]);
                }
            }
            Database::commit();
            logActivity('returns', 'CONFIRM_RETURN', "Retur {$ret['return_number']} dikonfirmasi");
            setFlash('success', "Retur {$ret['return_number']} dikonfirmasi. Stok & jurnal terupdate.");
        } catch (Exception $ex) {
            Database::rollback();
            setFlash('danger', 'Gagal: ' . $ex->getMessage());
        }
        redirect('index.php?page=sales_return');
    }

    if ($action === 'cancel') {
        $retId = (int)$_POST['id'];
        Database::query("UPDATE sales_returns SET status='CANCELLED' WHERE id=? AND status='DRAFT'", [$retId]);
        setFlash('warning', 'Retur dibatalkan.');
        redirect('index.php?page=sales_return');
    }
}

function module_render(): void
{
    $returns = Database::all(
        'SELECT r.*, s.so_number, c.name AS customer_name, u.full_name AS creator
         FROM sales_returns r
         JOIN sales_orders s ON s.id = r.so_id
         JOIN customers c ON c.id = s.customer_id
         LEFT JOIN users u ON u.id = r.created_by
         ORDER BY r.created_at DESC'
    );

    $createSoId = (int)($_GET['create'] ?? 0);
    $soItems = [];
    $so = null;
    if ($createSoId > 0) {
        $so = Database::row('SELECT s.*, c.name AS customer_name FROM sales_orders s JOIN customers c ON c.id = s.customer_id WHERE s.id = ?', [$createSoId]);
        if ($so) {
            $soItems = Database::all('SELECT i.*, p.code, p.name, p.unit FROM sales_order_items i JOIN products p ON p.id = i.product_id WHERE i.so_id = ?', [$createSoId]);
        }
    }

    $deliveredSOs = Database::all(
        "SELECT s.id, s.so_number, c.name AS customer_name, s.total FROM sales_orders s
         JOIN customers c ON c.id = s.customer_id WHERE s.status = 'DELIVERED' ORDER BY s.order_date DESC"
    );
    ?>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Daftar Retur Penjualan</h3>
            <div class="card-tools">
                <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#createRetModal"><i class="fas fa-plus"></i> Buat Retur</button>
            </div>
        </div>
        <div class="card-body">
            <table class="table table-bordered table-striped datatable">
                <thead><tr><th>No. Retur</th><th>No. SO</th><th>Customer</th><th>Tanggal</th><th class="text-right">Total</th><th>Status</th><th>Oleh</th><th width="100">Aksi</th></tr></thead>
                <tbody>
                <?php foreach ($returns as $r): ?>
                    <tr>
                        <td><?= e($r['return_number']) ?></td>
                        <td><a href="index.php?page=sales_view&id=<?= $r['so_id'] ?>"><?= e($r['so_number']) ?></a></td>
                        <td><?= e($r['customer_name']) ?></td>
                        <td><?= fdate($r['return_date']) ?></td>
                        <td class="text-right"><?= money($r['total']) ?></td>
                        <td><?= statusBadge($r['status']) ?></td>
                        <td><?= e($r['creator'] ?? '-') ?></td>
                        <td>
                            <button class="btn btn-sm btn-info" data-toggle="modal" data-target="#detailModal<?= $r['id'] ?>"><i class="fas fa-eye"></i></button>
                            <?php if ($r['status'] === 'DRAFT'): ?>
                                <form method="post" class="d-inline" onsubmit="return confirm('Konfirmasi retur ini? Stok & jurnal akan terupdate.')">
                                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                    <button name="action" value="confirm" class="btn btn-sm btn-success"><i class="fas fa-check"></i></button>
                                    <button name="action" value="cancel" class="btn btn-sm btn-danger" onclick="return confirm('Batalkan retur?')"><i class="fas fa-times"></i></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php foreach ($returns as $r):
        $items = Database::all('SELECT ri.*, p.code, p.name FROM sales_return_items ri JOIN products p ON p.id = ri.product_id WHERE ri.return_id = ?', [$r['id']]);
    ?>
    <div class="modal fade" id="detailModal<?= $r['id'] ?>">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title"><?= e($r['return_number']) ?> — <?= e($r['customer_name']) ?></h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button></div>
                <div class="modal-body">
                    <p><strong>Alasan:</strong> <?= e($r['reason'] ?? '-') ?></p>
                    <table class="table table-sm table-bordered">
                        <thead class="thead-light"><tr><th>Kode</th><th>Produk</th><th class="text-right">Qty</th><th class="text-right">Harga</th><th class="text-right">Subtotal</th></tr></thead>
                        <tbody>
                        <?php foreach ($items as $it): ?>
                            <tr><td><?= e($it['code']) ?></td><td><?= e($it['name']) ?></td><td class="text-right"><?= number_format($it['qty']) ?></td><td class="text-right"><?= money($it['price']) ?></td><td class="text-right"><?= money($it['subtotal']) ?></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot><tr><td colspan="4" class="text-right font-weight-bold">TOTAL</td><td class="text-right font-weight-bold"><?= money($r['total']) ?></td></tr></tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <div class="modal fade" id="createRetModal">
        <div class="modal-dialog">
            <form method="post" class="modal-content">
                <input type="hidden" name="action" value="create">
                <div class="modal-header"><h5 class="modal-title">Buat Retur Penjualan</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button></div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Pilih Sales Order (DELIVERED)</label>
                        <select name="so_id" class="form-control select2" required>
                            <option value="">- Pilih SO -</option>
                            <?php foreach ($deliveredSOs as $s): ?>
                                <option value="<?= $s['id'] ?>"><?= e($s['so_number']) ?> - <?= e($s['customer_name']) ?> (<?= money($s['total']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary"><i class="fas fa-arrow-right"></i> Lanjutkan</button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($createSoId > 0 && $so): ?>
    <div class="card card-warning">
        <div class="card-header"><h3 class="card-title"><i class="fas fa-undo"></i> Retur untuk <?= e($so['so_number']) ?> — <?= e($so['customer_name']) ?></h3></div>
        <form method="post">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="so_id" value="<?= $so['id'] ?>">
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-3">
                        <div class="form-group"><label>Tanggal Retur</label><input type="date" name="return_date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
                    </div>
                    <div class="col-md-9">
                        <div class="form-group"><label>Alasan</label><input type="text" name="reason" class="form-control" placeholder="cth: barang rusak, tidak sesuai" required></div>
                    </div>
                </div>
                <table class="table table-bordered">
                    <thead class="thead-light"><tr><th>Produk</th><th class="text-right">Qty Terjual</th><th width="120">Qty Retur</th><th class="text-right">Harga</th><th class="text-right">Subtotal</th></tr></thead>
                    <tbody>
                    <?php foreach ($soItems as $it): ?>
                        <tr>
                            <td><?= e($it['code']) ?> - <?= e($it['name']) ?></td>
                            <td class="text-right"><?= number_format($it['qty']) ?></td>
                            <td><input type="number" name="qty[<?= $it['id'] ?>]" class="form-control form-control-sm ret-qty" min="0" max="<?= $it['qty'] ?>" step="any" value="0"></td>
                            <td class="text-right"><input type="number" name="price[<?= $it['id'] ?>]" class="form-control form-control-sm ret-price" value="<?= $it['price'] ?>" step="any"></td>
                            <td class="text-right ret-subtotal">Rp 0</td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot><tr><td colspan="4" class="text-right font-weight-bold">TOTAL RETUR</td><td class="text-right font-weight-bold" id="retTotal">Rp 0</td></tr></tfoot>
                </table>
            </div>
            <div class="card-footer">
                <button class="btn btn-primary"><i class="fas fa-save"></i> Simpan Retur</button>
                <a href="index.php?page=sales_return" class="btn btn-secondary">Batal</a>
            </div>
        </form>
    </div>
    <?php endif; ?>
    <?php
}

function module_scripts(): void
{
    ?>
<script>
function recalcReturn() {
    var total = 0;
    $('tbody tr').each(function () {
        var qty = parseFloat($(this).find('.ret-qty').val()) || 0;
        var price = parseFloat($(this).find('.ret-price').val()) || 0;
        var sub = qty * price;
        total += sub;
        $(this).find('.ret-subtotal').text('Rp ' + sub.toLocaleString('id-ID'));
    });
    $('#retTotal').text('Rp ' + total.toLocaleString('id-ID'));
}
$(function () {
    $(document).on('input', '.ret-qty, .ret-price', recalcReturn);
});
</script>
    <?php
}
