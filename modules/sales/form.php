<?php
// -----------------------------------------------------
// Modul Sales: Form Buat Sales Order
// -----------------------------------------------------

$pageTitle = 'Buat Sales Order';

function module_handle(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

    $customerId = (int)$_POST['customer_id'];
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

    if ($customerId <= 0 || empty($validItems)) {
        setFlash('danger', 'Customer dan minimal satu item wajib diisi.');
        redirect('index.php?page=sales_form');
    }

    Database::begin();
    try {
        $soNumber = generateNumber('sales_orders', 'so_number', 'SO');
        Database::query(
            'INSERT INTO sales_orders (so_number, customer_id, order_date, status, total, notes, created_by)
             VALUES (?,?,?,?,?,?,?)',
            [$soNumber, $customerId, $orderDate, 'DRAFT', $total, $notes, Auth::user()['id']]
        );
        logActivity('sales', 'CREATE_SO', "Sales Order {$soNumber} dibuat");
        $soId = (int)Database::lastId();
        foreach ($validItems as $it) {
            Database::query(
                'INSERT INTO sales_order_items (so_id, product_id, qty, price, subtotal) VALUES (?,?,?,?,?)',
                [$soId, $it['product_id'], $it['qty'], $it['price'], $it['subtotal']]
            );
        }
        Database::commit();
        setFlash('success', "Sales Order {$soNumber} berhasil dibuat.");
        redirect('index.php?page=sales_view&id=' . $soId);
    } catch (Exception $ex) {
        Database::rollback();
        setFlash('danger', 'Gagal menyimpan SO: ' . $ex->getMessage());
        redirect('index.php?page=sales_form');
    }
}

function module_render(): void
{
    $customers = Database::all('SELECT * FROM customers ORDER BY name');
    $products = Database::all("SELECT * FROM products WHERE status = 1 ORDER BY name");
    ?>
    <div class="card card-info">
        <div class="card-header"><h3 class="card-title"><i class="fas fa-cash-register"></i> Form Sales Order</h3></div>
        <form method="post">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Customer</label>
                            <select name="customer_id" id="customerSelect" class="form-control select2" required>
                                <option value="">- Pilih Customer -</option>
                                <?php foreach ($customers as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= e($c['code']) ?> - <?= e($c['name']) ?></option>
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

                <h5 class="mt-3"><i class="fas fa-list"></i> Item Penjualan</h5>
                <table class="table table-bordered" id="itemsTable">
                    <thead class="thead-light">
                        <tr>
                            <th width="45%">Produk</th>
                            <th width="15%">Qty</th>
                            <th width="20%">Harga Jual</th>
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
                                        <option value="<?= $p['id'] ?>" data-price="<?= $p['selling_price'] ?>">
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
                <button class="btn btn-primary"><i class="fas fa-save"></i> Simpan SO</button>
                <a href="index.php?page=sales" class="btn btn-secondary">Batal</a>
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

function fetchPriceLevel(row) {
    var productId = row.find('.product-select').val();
    var customerId = $('#customerSelect').val();
    if (!productId) return;
    var url = 'index.php?page=api&action=price_level&product_id=' + productId;
    if (customerId) url += '&customer_id=' + customerId;
    $.getJSON(url, function (data) {
        row.find('.price-input').val(data.price);
        if (data.has_level) {
            row.find('.price-input').addClass('border-success').attr('title', 'Harga khusus customer ini');
        } else {
            row.find('.price-input').removeClass('border-success').removeAttr('title');
        }
        recalc();
    });
}

$(function () {
    $('#itemsTable').on('change', '.product-select', function () {
        fetchPriceLevel($(this).closest('tr'));
    });
    $('#customerSelect').on('change', function () {
        $('#itemsTable tbody tr').each(function () { fetchPriceLevel($(this)); });
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
