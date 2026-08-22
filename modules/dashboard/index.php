<?php
// -----------------------------------------------------
// Modul Dashboard - KPI & Grafik
// -----------------------------------------------------

$pageTitle = 'Dashboard';

function module_handle(): void
{
    // read-only
}

function module_render(): void
{
    $totalSales = Database::value("SELECT COALESCE(SUM(total),0) FROM sales_orders WHERE status != 'CANCELLED'");
    $totalPurchase = Database::value("SELECT COALESCE(SUM(total),0) FROM purchase_orders WHERE status != 'CANCELLED'");
    $totalProducts = Database::value("SELECT COUNT(*) FROM products WHERE status = 1");
    $totalCustomers = Database::value("SELECT COUNT(*) FROM customers");
    $lowStock = Database::all("SELECT * FROM products WHERE stock <= min_stock AND status = 1 ORDER BY stock ASC LIMIT 8");
    $recentSales = Database::all(
        "SELECT s.*, c.name AS customer_name FROM sales_orders s
         JOIN customers c ON c.id = s.customer_id ORDER BY s.created_at DESC LIMIT 5"
    );
    $recentPurchase = Database::all(
        "SELECT p.*, s.name AS supplier_name FROM purchase_orders p
         JOIN suppliers s ON s.id = p.supplier_id ORDER BY p.created_at DESC LIMIT 5"
    );

    // Data grafik penjualan vs pembelian 6 bulan terakhir
    $chartLabels = [];
    $salesData = [];
    $purchaseData = [];
    for ($i = 5; $i >= 0; $i--) {
        $month = date('Y-m', strtotime("-$i months"));
        $chartLabels[] = date('M Y', strtotime("-$i months"));
        $salesData[] = (float)Database::value(
            "SELECT COALESCE(SUM(total),0) FROM sales_orders WHERE DATE_FORMAT(order_date,'%Y-%m') = ? AND status != 'CANCELLED'",
            [$month]
        );
        $purchaseData[] = (float)Database::value(
            "SELECT COALESCE(SUM(total),0) FROM purchase_orders WHERE DATE_FORMAT(order_date,'%Y-%m') = ? AND status != 'CANCELLED'",
            [$month]
        );
    }

    // Data pie chart stok per kategori
    $stockByCategory = Database::all(
        "SELECT c.name, COALESCE(SUM(p.stock),0) AS total FROM categories c
         LEFT JOIN products p ON p.category_id = c.id AND p.status = 1
         GROUP BY c.id ORDER BY total DESC"
    );

    $GLOBALS['chartData'] = compact('chartLabels', 'salesData', 'purchaseData', 'stockByCategory');
    ?>
    <div class="row">
        <div class="col-lg-3 col-6">
            <div class="small-box bg-info">
                <div class="inner"><h3><?= money($totalSales) ?></h3><p>Total Penjualan</p></div>
                <div class="icon"><i class="fas fa-cash-register"></i></div>
                <a href="index.php?page=sales" class="small-box-footer">Detail <i class="fas fa-arrow-circle-right"></i></a>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="small-box bg-success">
                <div class="inner"><h3><?= money($totalPurchase) ?></h3><p>Total Pembelian</p></div>
                <div class="icon"><i class="fas fa-shopping-cart"></i></div>
                <a href="index.php?page=purchase" class="small-box-footer">Detail <i class="fas fa-arrow-circle-right"></i></a>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="small-box bg-warning">
                <div class="inner"><h3><?= $totalProducts ?></h3><p>Produk Aktif</p></div>
                <div class="icon"><i class="fas fa-box"></i></div>
                <a href="index.php?page=products" class="small-box-footer">Detail <i class="fas fa-arrow-circle-right"></i></a>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="small-box bg-danger">
                <div class="inner"><h3><?= $totalCustomers ?></h3><p>Customer</p></div>
                <div class="icon"><i class="fas fa-handshake"></i></div>
                <a href="index.php?page=customers" class="small-box-footer">Detail <i class="fas fa-arrow-circle-right"></i></a>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-chart-bar"></i> Penjualan vs Pembelian (6 Bulan)</h3></div>
                <div class="card-body"><canvas id="salesChart" style="height:280px"></canvas></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-chart-pie"></i> Stok per Kategori</h3></div>
                <div class="card-body"><canvas id="stockChart" style="height:280px"></canvas></div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4">
            <div class="card card-warning">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-exclamation-triangle"></i> Stok Menipis</h3></div>
                <div class="card-body p-0">
                    <table class="table table-sm">
                        <thead><tr><th>Produk</th><th class="text-right">Stok</th><th class="text-right">Min</th></tr></thead>
                        <tbody>
                        <?php foreach ($lowStock as $p): ?>
                            <tr>
                                <td><?= e($p['name']) ?></td>
                                <td class="text-right text-danger font-weight-bold"><?= number_format($p['stock']) ?></td>
                                <td class="text-right"><?= number_format($p['min_stock']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($lowStock)): ?>
                            <tr><td colspan="3" class="text-center text-muted">Semua stok aman</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-info">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-cash-register"></i> Penjualan Terbaru</h3></div>
                <div class="card-body p-0">
                    <table class="table table-sm">
                        <thead><tr><th>No. SO</th><th>Customer</th><th class="text-right">Total</th></tr></thead>
                        <tbody>
                        <?php foreach ($recentSales as $s): ?>
                            <tr>
                                <td><a href="index.php?page=sales_view&id=<?= $s['id'] ?>"><?= e($s['so_number']) ?></a></td>
                                <td><?= e($s['customer_name']) ?></td>
                                <td class="text-right"><?= money($s['total']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-success">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-shopping-cart"></i> Pembelian Terbaru</h3></div>
                <div class="card-body p-0">
                    <table class="table table-sm">
                        <thead><tr><th>No. PO</th><th>Supplier</th><th class="text-right">Total</th></tr></thead>
                        <tbody>
                        <?php foreach ($recentPurchase as $p): ?>
                            <tr>
                                <td><a href="index.php?page=purchase_view&id=<?= $p['id'] ?>"><?= e($p['po_number']) ?></a></td>
                                <td><?= e($p['supplier_name']) ?></td>
                                <td class="text-right"><?= money($p['total']) ?></td>
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
    $d = $GLOBALS['chartData'];
    ?>
<script>
$(function () {
    var ctx = document.getElementById('salesChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($d['chartLabels']) ?>,
            datasets: [
                { label: 'Penjualan', backgroundColor: '#17a2b8', data: <?= json_encode($d['salesData']) ?> },
                { label: 'Pembelian', backgroundColor: '#28a745', data: <?= json_encode($d['purchaseData']) ?> }
            ]
        },
        options: { responsive: true, maintainAspectRatio: false, scales: { yAxes: [{ ticks: { beginAtZero: true } }] } }
    });

    var pie = document.getElementById('stockChart').getContext('2d');
    new Chart(pie, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_column($d['stockByCategory'], 'name')) ?>,
            datasets: [{
                data: <?= json_encode(array_map('floatval', array_column($d['stockByCategory'], 'total'))) ?>,
                backgroundColor: ['#007bff','#28a745','#ffc107','#dc3545','#17a2b8','#6f42c1']
            }]
        },
        options: { responsive: true, maintainAspectRatio: false }
    });
});
</script>
    <?php
}
