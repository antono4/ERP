<?php
// -----------------------------------------------------
// Modul Master: Harga Bertingkat (Price Level per Customer)
// -----------------------------------------------------

$pageTitle = 'Harga Bertingkat';

function module_handle(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $productId = (int)$_POST['product_id'];
        $customerId = (int)$_POST['customer_id'];
        $price = (float)$_POST['price'];

        Database::query(
            'INSERT INTO price_levels (product_id, customer_id, price) VALUES (?,?,?)
             ON DUPLICATE KEY UPDATE price = VALUES(price)',
            [$productId, $customerId, $price]
        );
        $p = Database::value('SELECT name FROM products WHERE id = ?', [$productId]);
        $c = Database::value('SELECT name FROM customers WHERE id = ?', [$customerId]);
        logActivity('master', 'SET_PRICE', "Harga khusus {$p} untuk {$c}: " . money($price));
        setFlash('success', 'Harga bertingkat berhasil disimpan.');
        redirect('index.php?page=price_levels');
    }

    if ($action === 'delete') {
        Database::query('DELETE FROM price_levels WHERE id = ?', [(int)$_POST['id']]);
        setFlash('success', 'Harga bertingkat dihapus.');
        redirect('index.php?page=price_levels');
    }
}

function module_render(): void
{
    $levels = Database::all(
        'SELECT pl.*, p.code AS product_code, p.name AS product_name, p.selling_price AS base_price,
                c.name AS customer_name
         FROM price_levels pl
         JOIN products p ON p.id = pl.product_id
         JOIN customers c ON c.id = pl.customer_id
         ORDER BY c.name, p.code'
    );
    $products = Database::all("SELECT * FROM products WHERE status = 1 ORDER BY name");
    $customers = Database::all('SELECT * FROM customers ORDER BY name');
    ?>
    <div class="row">
        <div class="col-md-4">
            <div class="card card-primary">
                <div class="card-header"><h3 class="card-title">Set Harga Khusus</h3></div>
                <form method="post">
                    <input type="hidden" name="action" value="save">
                    <div class="card-body">
                        <div class="form-group">
                            <label>Customer</label>
                            <select name="customer_id" class="form-control select2" required>
                                <option value="">- Pilih Customer -</option>
                                <?php foreach ($customers as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= e($c['code']) ?> - <?= e($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Produk</label>
                            <select name="product_id" class="form-control select2" id="productSelect" required>
                                <option value="">- Pilih Produk -</option>
                                <?php foreach ($products as $p): ?>
                                    <option value="<?= $p['id'] ?>" data-base="<?= $p['selling_price'] ?>">
                                        <?= e($p['code']) ?> - <?= e($p['name']) ?> (<?= money($p['selling_price']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Harga Khusus</label>
                            <input type="number" name="price" id="priceInput" class="form-control" min="0" step="any" required>
                            <small class="text-muted" id="basePriceInfo"></small>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Daftar Harga Bertingkat</h3></div>
                <div class="card-body">
                    <table class="table table-bordered table-striped datatable">
                        <thead>
                            <tr>
                                <th>Customer</th><th>Produk</th><th class="text-right">Harga Normal</th>
                                <th class="text-right">Harga Khusus</th><th class="text-right">Diskon</th><th width="80">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($levels as $l):
                            $discount = $l['base_price'] > 0 ? round((1 - $l['price'] / $l['base_price']) * 100, 1) : 0;
                        ?>
                            <tr>
                                <td><?= e($l['customer_name']) ?></td>
                                <td><?= e($l['product_code']) ?> - <?= e($l['product_name']) ?></td>
                                <td class="text-right"><?= money($l['base_price']) ?></td>
                                <td class="text-right font-weight-bold text-primary"><?= money($l['price']) ?></td>
                                <td class="text-right">
                                    <?php if ($discount > 0): ?>
                                        <span class="badge badge-success"><?= $discount ?>%</span>
                                    <?php elseif ($discount < 0): ?>
                                        <span class="badge badge-danger">+<?= abs($discount) ?>%</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">0%</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="post" class="d-inline" onsubmit="return confirm('Hapus harga khusus ini?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $l['id'] ?>">
                                        <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                    </form>
                                </td>
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
    $('#productSelect').on('change', function () {
        var base = parseFloat($(this).find(':selected').data('base')) || 0;
        $('#basePriceInfo').text('Harga normal: Rp ' + base.toLocaleString('id-ID'));
        if (!$('#priceInput').val()) $('#priceInput').val(base);
    });
});
</script>
    <?php
}
