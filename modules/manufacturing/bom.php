<?php
// -----------------------------------------------------
// Modul Manufacturing: Bill of Materials (BOM)
// -----------------------------------------------------

$pageTitle = 'Bill of Materials (BOM)';

function module_handle(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $productId = (int)$_POST['product_id'];
        $componentIds = $_POST['component_id'] ?? [];
        $qtys = $_POST['qty'] ?? [];

        Database::query('DELETE FROM bom_items WHERE product_id = ?', [$productId]);
        $saved = 0;
        foreach ($componentIds as $i => $cid) {
            $cid = (int)$cid;
            $qty = (float)($qtys[$i] ?? 0);
            if ($cid > 0 && $qty > 0 && $cid !== $productId) {
                Database::query('INSERT INTO bom_items (product_id, component_id, qty) VALUES (?,?,?)', [$productId, $cid, $qty]);
                $saved++;
            }
        }
        $p = Database::value('SELECT name FROM products WHERE id = ?', [$productId]);
        logActivity('manufacturing', 'SET_BOM', "BOM untuk {$p}: {$saved} komponen");
        setFlash('success', "BOM untuk {$p} disimpan ({$saved} komponen).");
        redirect('index.php?page=bom&product_id=' . $productId);
    }
}

function module_render(): void
{
    $products = Database::all("SELECT * FROM products WHERE status = 1 ORDER BY name");
    $productId = (int)($_GET['product_id'] ?? 0);
    $components = [];
    $product = null;
    if ($productId > 0) {
        $product = Database::row('SELECT * FROM products WHERE id = ?', [$productId]);
        $components = Database::all(
            'SELECT b.*, p.code, p.name, p.unit, p.purchase_price, p.stock
             FROM bom_items b JOIN products p ON p.id = b.component_id WHERE b.product_id = ?',
            [$productId]
        );
    }

    // Produk yang punya BOM
    $bomProducts = Database::all(
        'SELECT p.id, p.code, p.name, COUNT(b.id) AS comp_count, SUM(b.qty * c.purchase_price) AS est_cost
         FROM products p
         JOIN bom_items b ON b.product_id = p.id
         JOIN products c ON c.id = b.component_id
         GROUP BY p.id ORDER BY p.code'
    );
    ?>
    <div class="row">
        <div class="col-md-5">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Produk dengan BOM</h3></div>
                <div class="card-body p-0">
                    <table class="table table-sm">
                        <thead><tr><th>Produk</th><th class="text-right">Komponen</th><th class="text-right">Est. Biaya/Unit</th><th width="60"></th></tr></thead>
                        <tbody>
                        <?php foreach ($bomProducts as $bp): ?>
                            <tr class="<?= $productId === (int)$bp['id'] ? 'table-primary' : '' ?>">
                                <td><?= e($bp['code']) ?> - <?= e($bp['name']) ?></td>
                                <td class="text-right"><span class="badge badge-info"><?= $bp['comp_count'] ?></span></td>
                                <td class="text-right"><?= money($bp['est_cost']) ?></td>
                                <td><a href="index.php?page=bom&product_id=<?= $bp['id'] ?>" class="btn btn-sm btn-primary"><i class="fas fa-edit"></i></a></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($bomProducts)): ?>
                            <tr><td colspan="4" class="text-center text-muted">Belum ada BOM. Pilih produk di form kanan.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-7">
            <div class="card card-primary">
                <div class="card-header"><h3 class="card-title"><?= $product ? 'Edit BOM: ' . e($product['name']) : 'Buat/Edit BOM' ?></h3></div>
                <form method="post">
                    <input type="hidden" name="action" value="save">
                    <div class="card-body">
                        <div class="form-group">
                            <label>Produk Jadi (Finished Good)</label>
                            <select name="product_id" class="form-control select2" id="fgSelect" onchange="location='index.php?page=bom&product_id='+this.value" required>
                                <option value="">- Pilih Produk -</option>
                                <?php foreach ($products as $p): ?>
                                    <option value="<?= $p['id'] ?>" <?= $productId === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['code']) ?> - <?= e($p['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if ($product): ?>
                        <h5 class="mt-3"><i class="fas fa-cogs"></i> Komponen / Bahan Baku</h5>
                        <table class="table table-bordered" id="bomTable">
                            <thead class="thead-light"><tr><th width="55%">Komponen</th><th width="20%">Qty per Unit</th><th width="20%" class="text-right">Est. Biaya</th><th width="5%"></th></tr></thead>
                            <tbody>
                            <?php if (!empty($components)): ?>
                                <?php foreach ($components as $comp): ?>
                                <tr class="bom-row">
                                    <td>
                                        <select name="component_id[]" class="form-control comp-select" required>
                                            <option value="">- Pilih -</option>
                                            <?php foreach ($products as $p): if ($p['id'] == $productId) continue; ?>
                                                <option value="<?= $p['id'] ?>" data-price="<?= $p['purchase_price'] ?>" <?= (int)$comp['component_id'] === (int)$p['id'] ? 'selected' : '' ?>>
                                                    <?= e($p['code']) ?> - <?= e($p['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td><input type="number" name="qty[]" class="form-control comp-qty" min="0.0001" step="any" value="<?= $comp['qty'] ?>" required></td>
                                    <td class="text-right comp-cost"><?= money($comp['qty'] * $comp['purchase_price']) ?></td>
                                    <td><button type="button" class="btn btn-danger btn-sm remove-bom-row"><i class="fas fa-times"></i></button></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr class="bom-row">
                                    <td>
                                        <select name="component_id[]" class="form-control comp-select" required>
                                            <option value="">- Pilih -</option>
                                            <?php foreach ($products as $p): if ($p['id'] == $productId) continue; ?>
                                                <option value="<?= $p['id'] ?>" data-price="<?= $p['purchase_price'] ?>"><?= e($p['code']) ?> - <?= e($p['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td><input type="number" name="qty[]" class="form-control comp-qty" min="0.0001" step="any" value="1" required></td>
                                    <td class="text-right comp-cost">Rp 0</td>
                                    <td><button type="button" class="btn btn-danger btn-sm remove-bom-row"><i class="fas fa-times"></i></button></td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                            <tfoot><tr><td colspan="2" class="text-right font-weight-bold">Estimasi Biaya per Unit</td><td class="text-right font-weight-bold" id="bomTotal">Rp 0</td><td></td></tr></tfoot>
                        </table>
                        <button type="button" class="btn btn-success btn-sm" id="addBomRow"><i class="fas fa-plus"></i> Tambah Komponen</button>
                        <?php else: ?>
                            <p class="text-muted">Pilih produk jadi terlebih dahulu untuk mengatur BOM-nya.</p>
                        <?php endif; ?>
                    </div>
                    <?php if ($product): ?>
                    <div class="card-footer">
                        <button class="btn btn-primary"><i class="fas fa-save"></i> Simpan BOM</button>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
    <?php
}

function module_scripts(): void
{
    ?>
<script>
function recalcBom() {
    var total = 0;
    $('.bom-row').each(function () {
        var price = parseFloat($(this).find('.comp-select :selected').data('price')) || 0;
        var qty = parseFloat($(this).find('.comp-qty').val()) || 0;
        var cost = price * qty;
        total += cost;
        $(this).find('.comp-cost').text('Rp ' + cost.toLocaleString('id-ID'));
    });
    $('#bomTotal').text('Rp ' + total.toLocaleString('id-ID'));
}
$(function () {
    $('#bomTable').on('change input', '.comp-select, .comp-qty', recalcBom);
    $('#addBomRow').click(function () {
        var row = $('.bom-row:first').clone();
        row.find('select').val('');
        row.find('.comp-qty').val(1);
        row.find('.comp-cost').text('Rp 0');
        $('#bomTable tbody').append(row);
    });
    $('#bomTable').on('click', '.remove-bom-row', function () {
        if ($('.bom-row').length > 1) { $(this).closest('tr').remove(); recalcBom(); }
    });
    recalcBom();
});
</script>
    <?php
}
