<?php
// -----------------------------------------------------
// Modul Inventory: Stock Opname - Form Buat
// -----------------------------------------------------

$pageTitle = 'Buat Stock Opname';

function module_handle(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

    $opnameDate = $_POST['opname_date'];
    $notes = trim($_POST['notes'] ?? '');
    $productIds = $_POST['product_id'] ?? [];

    if (empty($productIds)) {
        setFlash('danger', 'Pilih minimal satu produk.');
        redirect('index.php?page=opname_form');
    }

    Database::begin();
    try {
        $opnameNo = generateNumber('stock_opnames', 'opname_number', 'OPN');
        Database::query(
            'INSERT INTO stock_opnames (opname_number, opname_date, status, notes, created_by) VALUES (?,?,?,?,?)',
            [$opnameNo, $opnameDate, 'OPEN', $notes, Auth::user()['id']]
        );
        $opnameId = (int)Database::lastId();

        foreach ($productIds as $pid) {
            $pid = (int)$pid;
            $stock = Database::value('SELECT stock FROM products WHERE id = ?', [$pid]);
            Database::query(
                'INSERT INTO stock_opname_items (opname_id, product_id, system_qty) VALUES (?,?,?)',
                [$opnameId, $pid, $stock]
            );
        }
        Database::commit();
        logActivity('inventory', 'OPNAME_CREATE', "Stock opname {$opnameNo} dibuat");
        setFlash('success', "Opname {$opnameNo} dibuat. Silakan input hasil hitung fisik.");
        redirect('index.php?page=opname_view&id=' . $opnameId);
    } catch (Exception $ex) {
        Database::rollback();
        setFlash('danger', 'Gagal: ' . $ex->getMessage());
        redirect('index.php?page=opname_form');
    }
}

function module_render(): void
{
    $products = Database::all("SELECT * FROM products WHERE status = 1 ORDER BY code");
    $categories = Database::all('SELECT * FROM categories ORDER BY name');
    ?>
    <div class="card card-primary">
        <div class="card-header"><h3 class="card-title"><i class="fas fa-clipboard-check"></i> Buat Stock Opname Baru</h3></div>
        <form method="post">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group"><label>Tanggal Opname</label><input type="date" name="opname_date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
                    </div>
                    <div class="col-md-9">
                        <div class="form-group"><label>Catatan</label><input type="text" name="notes" class="form-control" placeholder="cth: opname rutin bulanan"></div>
                    </div>
                </div>

                <h5 class="mt-3"><i class="fas fa-boxes"></i> Pilih Produk yang Akan Dihitung</h5>
                <div class="mb-3">
                    <button type="button" class="btn btn-sm btn-outline-primary" id="selectAll">Pilih Semua</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="deselectAll">Batal Pilih</button>
                    <select class="form-control form-control-sm d-inline-block ml-2" style="width:auto" id="filterCat">
                        <option value="">Semua Kategori</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <table class="table table-bordered table-sm">
                    <thead class="thead-light">
                        <tr><th width="50"><input type="checkbox" id="checkAll"></th><th>Kode</th><th>Produk</th><th>Kategori</th><th class="text-right">Stok Sistem</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($products as $p): ?>
                        <tr class="product-row" data-cat="<?= $p['category_id'] ?>">
                            <td><input type="checkbox" name="product_id[]" value="<?= $p['id'] ?>" class="product-check"></td>
                            <td><?= e($p['code']) ?></td>
                            <td><?= e($p['name']) ?></td>
                            <td><?= e($p['category_id'] ? Database::value('SELECT name FROM categories WHERE id=?', [$p['category_id']]) : '-') ?></td>
                            <td class="text-right"><?= number_format($p['stock']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer">
                <button class="btn btn-primary"><i class="fas fa-save"></i> Buat Opname</button>
                <a href="index.php?page=opname" class="btn btn-secondary">Batal</a>
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
    $('#checkAll').change(function () { $('.product-check').prop('checked', $(this).is(':checked')); });
    $('#selectAll').click(function () { $('.product-row:visible .product-check').prop('checked', true); });
    $('#deselectAll').click(function () { $('.product-check').prop('checked', false); });
    $('#filterCat').change(function () {
        var cat = $(this).val();
        if (cat === '') { $('.product-row').show(); } else {
            $('.product-row').each(function () {
                $(this).toggle($(this).data('cat') == cat);
            });
        }
    });
});
</script>
    <?php
}
