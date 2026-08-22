<?php
// -----------------------------------------------------
// Modul WMS: Form Transfer Stok (L78)
// -----------------------------------------------------

$pageTitle = 'Buat Transfer Stok';

function module_handle(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

    $from = (int)$_POST['from_warehouse'];
    $to = (int)$_POST['to_warehouse'];
    $date = $_POST['transfer_date'];
    $notes = trim($_POST['notes'] ?? '');
    $productIds = $_POST['product_id'] ?? [];
    $qtys = $_POST['qty'] ?? [];

    if ($from === $to) {
        setFlash('danger', 'Gudang asal dan tujuan tidak boleh sama.');
        redirect('index.php?page=transfer_form');
    }

    $validItems = [];
    foreach ($productIds as $i => $pid) {
        $pid = (int)$pid;
        $qty = (float)($qtys[$i] ?? 0);
        if ($pid > 0 && $qty > 0) {
            $validItems[] = ['product_id' => $pid, 'qty' => $qty];
        }
    }

    if (empty($validItems)) {
        setFlash('danger', 'Minimal satu item harus diisi.');
        redirect('index.php?page=transfer_form');
    }

    Database::begin();
    try {
        $trNo = generateNumber('stock_transfers', 'transfer_number', 'TRF');
        Database::query(
            'INSERT INTO stock_transfers (transfer_number, from_warehouse, to_warehouse, transfer_date, status, notes, created_by) VALUES (?,?,?,?,\'DRAFT\',?,?)',
            [$trNo, $from, $to, $date, $notes, Auth::user()['id']]
        );
        $trId = (int)Database::lastId();
        foreach ($validItems as $it) {
            Database::query('INSERT INTO stock_transfer_items (transfer_id, product_id, qty) VALUES (?,?,?)', [$trId, $it['product_id'], $it['qty']]);
        }
        Database::commit();
        logActivity('wms', 'CREATE_TRANSFER', "Transfer {$trNo} dibuat");
        setFlash('success', "Transfer {$trNo} dibuat. Konfirmasi untuk memindahkan stok.");
        redirect('index.php?page=transfer_view&id=' . $trId);
    } catch (Exception $ex) {
        Database::rollback();
        setFlash('danger', 'Gagal: ' . $ex->getMessage());
        redirect('index.php?page=transfer_form');
    }
}

function module_render(): void
{
    $warehouses = Database::all('SELECT * FROM warehouses ORDER BY default_flag DESC, name');
    $products = Database::all("SELECT * FROM products WHERE status = 1 ORDER BY name");
    ?>
    <div class="card card-primary">
        <div class="card-header"><h3 class="card-title"><i class="fas fa-truck-moving"></i> Form Transfer Stok</h3></div>
        <form method="post">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Dari Gudang</label>
                            <select name="from_warehouse" class="form-control select2" required>
                                <option value="">- Pilih -</option>
                                <?php foreach ($warehouses as $w): ?>
                                    <option value="<?= $w['id'] ?>"><?= e($w['code']) ?> - <?= e($w['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Ke Gudang</label>
                            <select name="to_warehouse" class="form-control select2" required>
                                <option value="">- Pilih -</option>
                                <?php foreach ($warehouses as $w): ?>
                                    <option value="<?= $w['id'] ?>"><?= e($w['code']) ?> - <?= e($w['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group"><label>Tanggal</label><input type="date" name="transfer_date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group"><label>Catatan</label><input type="text" name="notes" class="form-control"></div>
                    </div>
                </div>

                <h5 class="mt-3"><i class="fas fa-list"></i> Item Transfer</h5>
                <table class="table table-bordered" id="trfTable">
                    <thead class="thead-light"><tr><th width="60%">Produk</th><th width="15%">Qty</th><th width="20%" class="text-right">Stok Saat Ini</th><th width="5%"></th></tr></thead>
                    <tbody>
                        <tr class="trf-row">
                            <td>
                                <select name="product_id[]" class="form-control trf-product" required>
                                    <option value="">- Pilih -</option>
                                    <?php foreach ($products as $p): ?>
                                        <option value="<?= $p['id'] ?>" data-stock="<?= $p['stock'] ?>"><?= e($p['code']) ?> - <?= e($p['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input type="number" name="qty[]" class="form-control trf-qty" min="1" step="any" value="1" required></td>
                            <td class="text-right trf-stock">0</td>
                            <td><button type="button" class="btn btn-danger btn-sm remove-trf-row"><i class="fas fa-times"></i></button></td>
                        </tr>
                    </tbody>
                </table>
                <button type="button" class="btn btn-success btn-sm" id="addTrfRow"><i class="fas fa-plus"></i> Tambah Baris</button>
            </div>
            <div class="card-footer">
                <button class="btn btn-primary"><i class="fas fa-save"></i> Simpan Transfer</button>
                <a href="index.php?page=transfers" class="btn btn-secondary">Batal</a>
            </div>
        </form>
    </div>
    <?php
}

function module_scripts(): void
{
    ?>
<script>
$(function () {
    $('#trfTable').on('change', '.trf-product', function () {
        var stock = $(this).find(':selected').data('stock') || 0;
        $(this).closest('tr').find('.trf-stock').text(Number(stock).toLocaleString('id-ID'));
    });
    $('#addTrfRow').click(function () {
        var row = $('.trf-row:first').clone();
        row.find('select').val('');
        row.find('.trf-qty').val(1);
        row.find('.trf-stock').text('0');
        $('#trfTable tbody').append(row);
    });
    $('#trfTable').on('click', '.remove-trf-row', function () {
        if ($('.trf-row').length > 1) $(this).closest('tr').remove();
    });
});
</script>
    <?php
}
