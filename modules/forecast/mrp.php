<?php
// -----------------------------------------------------
// Modul Forecast: Forecast Penjualan & MRP
// -----------------------------------------------------

$pageTitle = 'Forecast & MRP';

function module_handle(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $action = $_POST['action'] ?? '';

    if ($action === 'forecast') {
        $productId = (int)$_POST['product_id'];
        $period = $_POST['period'];
        $qty = (float)$_POST['forecast_qty'];
        Database::query(
            'INSERT INTO sales_forecasts (product_id, period, forecast_qty) VALUES (?,?,?)
             ON DUPLICATE KEY UPDATE forecast_qty = VALUES(forecast_qty)',
            [$productId, $period, $qty]
        );
        setFlash('success', 'Forecast disimpan.');
        redirect('index.php?page=forecast');
    }

    if ($action === 'generate_mrp') {
        $period = $_POST['period'];
        // Ambil forecast periode ini, hitung kebutuhan berdasarkan BOM
        $forecasts = Database::all('SELECT * FROM sales_forecasts WHERE period = ?', [$period]);
        $created = 0;
        foreach ($forecasts as $f) {
            $bom = Database::all('SELECT * FROM bom_items WHERE product_id = ?', [$f['product_id']]);
            foreach ($bom as $b) {
                $needed = $b['qty'] * $f['forecast_qty'];
                $currentStock = Database::value('SELECT stock FROM products WHERE id = ?', [$b['component_id']]);
                if ($currentStock < $needed) {
                    $suggest = $needed - $currentStock;
                    Database::query(
                        'INSERT INTO mrp_suggestions (product_id, suggested_qty, reason, status) VALUES (?,?,?,?)',
                        [$b['component_id'], $suggest, "Kebutuhan produksi periode {$period} untuk " . Database::value('SELECT name FROM products WHERE id = ?', [$f['product_id']]), 'PENDING']
                    );
                    $created++;
                }
            }
        }
        setFlash('success', "MRP: {$created} saran pembelian dibuat.");
        redirect('index.php?page=forecast');
    }
}

function module_render(): void
{
    $products = Database::all("SELECT * FROM products WHERE status = 1 ORDER BY name");
    $forecasts = Database::all(
        'SELECT f.*, p.code, p.name, p.stock FROM sales_forecasts f JOIN products p ON p.id = f.product_id ORDER BY f.period DESC, p.code'
    );
    $mrp = Database::all(
        'SELECT m.*, p.code, p.name, p.stock FROM mrp_suggestions m JOIN products p ON p.id = m.product_id ORDER BY m.created_at DESC LIMIT 50'
    );
    ?>
    <div class="row">
        <div class="col-md-4">
            <div class="card card-primary">
                <div class="card-header"><h3 class="card-title">Buat Forecast</h3></div>
                <form method="post">
                    <input type="hidden" name="action" value="forecast">
                    <div class="card-body">
                        <div class="form-group">
                            <label>Produk</label>
                            <select name="product_id" class="form-control select2" required>
                                <option value="">- Pilih -</option>
                                <?php foreach ($products as $p): ?>
                                    <option value="<?= $p['id'] ?>"><?= e($p['code']) ?> - <?= e($p['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group"><label>Periode</label><input type="month" name="period" class="form-control" value="<?= date('Y-m') ?>" required></div>
                        <div class="form-group"><label>Qty Forecast</label><input type="number" name="forecast_qty" class="form-control" min="1" step="any" required></div>
                    </div>
                    <div class="card-footer">
                        <button class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
                    </div>
                </form>
            </div>

            <div class="card card-warning">
                <div class="card-header"><h3 class="card-title">Generate MRP</h3></div>
                <form method="post">
                    <input type="hidden" name="action" value="generate_mrp">
                    <div class="card-body">
                        <div class="form-group"><label>Periode</label><input type="month" name="period" class="form-control" value="<?= date('Y-m') ?>" required></div>
                        <small class="text-muted">Sistem akan menghitung kebutuhan bahan berdasarkan forecast + BOM dan membandingkan dengan stok saat ini.</small>
                    </div>
                    <div class="card-footer">
                        <button class="btn btn-warning"><i class="fas fa-cogs"></i> Generate MRP</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-chart-line"></i> Daftar Forecast</h3></div>
                <div class="card-body p-0">
                    <table class="table table-sm">
                        <thead><tr><th>Produk</th><th>Periode</th><th class="text-right">Forecast</th><th class="text-right">Stok Saat Ini</th></tr></thead>
                        <tbody>
                        <?php foreach ($forecasts as $f): ?>
                            <tr>
                                <td><?= e($f['code']) ?> - <?= e($f['name']) ?></td>
                                <td><?= e($f['period']) ?></td>
                                <td class="text-right"><?= number_format($f['forecast_qty']) ?></td>
                                <td class="text-right"><?= number_format($f['stock']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-lightbulb"></i> MRP Suggestions</h3></div>
                <div class="card-body p-0">
                    <table class="table table-sm">
                        <thead><tr><th>Produk</th><th class="text-right">Saran Qty</th><th>Alasan</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($mrp as $m): ?>
                            <tr>
                                <td><?= e($m['code']) ?> - <?= e($m['name']) ?></td>
                                <td class="text-right"><?= number_format($m['suggested_qty']) ?></td>
                                <td><?= e($m['reason']) ?></td>
                                <td><?= statusBadge($m['status']) ?></td>
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
