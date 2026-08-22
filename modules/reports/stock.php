<?php
// -----------------------------------------------------
// Modul Report: Laporan Stok
// -----------------------------------------------------

$pageTitle = 'Laporan Stok';

function module_handle(): void
{
    // read-only
}

function module_render(): void
{
    $categoryFilter = (int)($_GET['category_id'] ?? 0);
    $categories = Database::all('SELECT * FROM categories ORDER BY name');

    $sql = "SELECT p.*, c.name AS category_name,
                (p.stock * p.purchase_price) AS stock_value,
                (p.stock * p.selling_price) AS potential_value
            FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.status = 1";
    $params = [];
    if ($categoryFilter > 0) {
        $sql .= " AND p.category_id = ?";
        $params[] = $categoryFilter;
    }
    $sql .= " ORDER BY p.code";
    $products = Database::all($sql, $params);
    $totalValue = array_sum(array_column($products, 'stock_value'));
    $totalPotential = array_sum(array_column($products, 'potential_value'));
    ?>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-warehouse"></i> Laporan Stok Barang</h3>
            <div class="card-tools no-print">
                <button class="btn btn-sm btn-secondary" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
            </div>
        </div>
        <div class="card-body">
            <form method="get" class="form-inline mb-3 no-print">
                <input type="hidden" name="page" value="report_stock">
                <select name="category_id" class="form-control mr-2">
                    <option value="0">Semua Kategori</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $categoryFilter === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-primary"><i class="fas fa-search"></i> Tampilkan</button>
            </form>
            <table class="table table-bordered table-striped">
                <thead class="thead-light">
                    <tr>
                        <th>Kode</th><th>Produk</th><th>Kategori</th><th>Satuan</th>
                        <th class="text-right">Stok</th>
                        <th class="text-right">Harga Beli</th>
                        <th class="text-right">Nilai Persediaan</th>
                        <th class="text-right">Potensi Penjualan</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($products as $p): ?>
                    <tr>
                        <td><?= e($p['code']) ?></td>
                        <td><?= e($p['name']) ?></td>
                        <td><?= e($p['category_name'] ?? '-') ?></td>
                        <td><?= e($p['unit']) ?></td>
                        <td class="text-right"><?= number_format($p['stock']) ?></td>
                        <td class="text-right"><?= money($p['purchase_price']) ?></td>
                        <td class="text-right"><?= money($p['stock_value']) ?></td>
                        <td class="text-right"><?= money($p['potential_value']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="6" class="text-right font-weight-bold">TOTAL</td>
                        <td class="text-right font-weight-bold"><?= money($totalValue) ?></td>
                        <td class="text-right font-weight-bold"><?= money($totalPotential) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    <?php
}
