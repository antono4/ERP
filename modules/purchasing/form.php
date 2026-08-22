<?php
// -----------------------------------------------------
// Modul Purchasing: Form Buat Purchase Order
// -----------------------------------------------------

$pageTitle = 'Buat Purchase Order';

function module_handle(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

    $supplierId = (int)$_POST['supplier_id'];
    $orderDate = $_POST['order_date'];
    $notes = trim($_POST['notes'] ?? '');
    $productIds = $_POST['product_id'] ?? [];
    $qtys = $_POST['qty'] ?? [];
    $prices = $_POST['price'] ?? [];

    $validItems = [];
    $total = 0;
    foreach ($productIds as $i => $pid) {
        $pid = (int)$pid;
        $qty = (float)($qtys[$i] ?? 0);
        $price = (float)($prices[$i] ?? 0);
        if ($pid > 0 && $qty > 0) {
            $subtotal = $qty * $price;
            $validItems[] = ['product_id' => $pid, 'qty' => $qty, 'price' => $price, 'subtotal' => $subtotal];
            $total += $subtotal;
        }
    }

    if ($supplierId <= 0 || empty($validItems)) {
        setFlash('danger', 'Supplier dan minimal satu item wajib diisi.');
        redirect('index.php?page=purchase_form');
    }

    Database::begin();
    try {
        $poNumber = generateNumber('purchase_orders', 'po_number', 'PO');
        Database::query(
            'INSERT INTO purchase_orders (po_number, supplier_id, order_date, status, total, notes, created_by)
             VALUES (?,?,?,?,?,?,?)',
            [$poNumber, $supplierId, $orderDate, 'DRAFT', $total, $notes, Auth::user()['id']]
        );
        $poId = (int)Database::lastId();
        foreach ($validItems as $it) {
            Database::query(
                'INSERT INTO purchase_order_items (po_id, product_id, qty, price, subtotal) VALUES (?,?,?,?,?)',
                [$poId, $it['product_id'], $it['qty'], $it['price'], $it['subtotal']]
            );
        }
        Database::commit();
        setFlash('success', "Purchase Order {$poNumber} berhasil dibuat.");
        redirect('index.php?page=purchase_view&id=' . $poId);
    } catch (Exception $ex) {
        Database::rollback();
        setFlash('danger', 'Gagal menyimpan PO: ' . $ex->getMessage());
        redirect('index.php?page=purchase_form');
    }
}

function module_render(): void
{
    $suppliers = Database::all('SELECT * FROM suppliers ORDER BY name');
    $products = Database::all("SELECT * FROM products WHERE status = 1 ORDER BY name");
    ?>
    <div class="card card-primary">
        <div class="card-header"><h3 class="card-title"><i class="fas fa-shopping-cart"></i> Form Purchase Order</h3></div>
        <form method="post">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Supplier</label>
                            <select name="supplier_id" class="form-control select2" required>
                                <option value="">- Pilih Supplier -</option>
                                <?php foreach ($suppliers as $s): ?>
                                    <option value="<?= $s['id'] ?>"><?= e($s['code']) ?> - <?= e($s['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Tanggal Order</label>
                            <input type="date" name="order_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>
                    <div class="col-md-5">
                        <div class="form-group"><label>Catatan</label><input type="text" name="notes" class="form-control"></div>
                    </div>
                </div>

                <h5 class="mt-3"><i class="fas fa-list"></i> Item Pembelian</h5>
                <table class="table table-bordered" id="itemsTable">
                    <thead class="thead-light">
                        <tr>
                            <th width="45%">Produk</th>
                            <th width="15%">Qty</th>
                            <th width="20%">Harga Beli</th>
                            <th width="15%" class="text-right">Subtotal</th>
                            <th width="5%"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="item-row">
                            <td>
                                <select name="product_id[]" class="form-control product-select" required>
                                    <option value="">- Pilih Produk -</option>
                                    <?php foreach ($products as $p): ?>
                                        <option value="<?= $p['id'] ?>" data-price="<?= $p['purchase_price'] ?>">
                                            <?= e($p['code']) ?> - <?= e($p['name']) ?> (Stok: <?= number_format($p['stock']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input type="number" name="qty[]" class="form-control qty-input" min="1" step="any" value="1" required></td>
                            <td><input type="number" name="price[]" class="form-control price-input" min="0" step="any" value="0" required></td>
                            <td class="text-right subtotal-cell">Rp 0</td>
                            <td><button type="button" class="btn btn-danger btn-sm remove-row"><i class="fas fa-times"></i></button></td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3" class="text-right font-weight-bold">TOTAL</td>
                            <td class="text-right font-weight-bold" id="grandTotal">Rp 0</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
                <button type="button" class="btn btn-success btn-sm" id="addRow"><i class="fas fa-plus"></i> Tambah Baris</button>
            </div>
            <div class="card-footer">
                <button class="btn btn-primary"><i class="fas fa-save"></i> Simpan PO</button>
                <a href="index.php?page=purchase" class="btn btn-secondary">Batal</a>
            </div>
        </form>
    </div>
    <?php
}

function module_scripts(): void
{
    ?>
<script>
function recalc() {
    var total = 0;
    $('#itemsTable tbody tr').each(function () {
        var qty = parseFloat($(this).find('.qty-input').val()) || 0;
        var price = parseFloat($(this).find('.price-input').val()) || 0;
        var subtotal = qty * price;
        total += subtotal;
        $(this).find('.subtotal-cell').text('Rp ' + subtotal.toLocaleString('id-ID'));
    });
    $('#grandTotal').text('Rp ' + total.toLocaleString('id-ID'));
}
$(function () {
    $('#itemsTable').on('change', '.product-select', function () {
        var price = $(this).find(':selected').data('price') || 0;
        $(this).closest('tr').find('.price-input').val(price);
        recalc();
    });
    $('#itemsTable').on('input', '.qty-input, .price-input', recalc);
    $('#addRow').click(function () {
        var row = $('#itemsTable tbody tr:first').clone();
        row.find('input, select').val('');
        row.find('.qty-input').val(1);
        row.find('.price-input').val(0);
        row.find('.subtotal-cell').text('Rp 0');
        $('#itemsTable tbody').append(row);
    });
    $('#itemsTable').on('click', '.remove-row', function () {
        if ($('#itemsTable tbody tr').length > 1) {
            $(this).closest('tr').remove();
            recalc();
        }
    });
});
</script>
    <?php
}
