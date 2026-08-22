<?php
// -----------------------------------------------------
// Modul Report: Laporan Penjualan
// -----------------------------------------------------

$pageTitle = 'Laporan Penjualan';

function module_handle(): void
{
    if (isset($_GET['export']) && $_GET['export'] === 'csv') {
        $from = $_GET['from'] ?? date('Y-m-01');
        $to = $_GET['to'] ?? date('Y-m-d');
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="laporan_penjualan_' . $from . '_' . $to . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['No. SO', 'Tanggal', 'Customer', 'Status', 'Total']);
        $rows = Database::all(
            "SELECT s.so_number, s.order_date, c.name AS customer_name, s.status, s.total
             FROM sales_orders s JOIN customers c ON c.id = s.customer_id
             WHERE s.order_date BETWEEN ? AND ? AND s.status != 'CANCELLED'
             ORDER BY s.order_date",
            [$from, $to]
        );
        foreach ($rows as $r) {
            fputcsv($out, [$r['so_number'], $r['order_date'], $r['customer_name'], $r['status'], $r['total']]);
        }
        fclose($out);
        exit;
    }
}

function module_render(): void
{
    $from = $_GET['from'] ?? date('Y-m-01');
    $to = $_GET['to'] ?? date('Y-m-d');

    $rows = Database::all(
        "SELECT s.so_number, s.order_date, c.name AS customer_name, s.status, s.total
         FROM sales_orders s JOIN customers c ON c.id = s.customer_id
         WHERE s.order_date BETWEEN ? AND ? AND s.status != 'CANCELLED'
         ORDER BY s.order_date",
        [$from, $to]
    );
    $grandTotal = array_sum(array_column($rows, 'total'));

    $topProducts = Database::all(
        "SELECT p.code, p.name, SUM(i.qty) AS total_qty, SUM(i.subtotal) AS total_amount
         FROM sales_order_items i
         JOIN sales_orders s ON s.id = i.so_id
         JOIN products p ON p.id = i.product_id
         WHERE s.order_date BETWEEN ? AND ? AND s.status != 'CANCELLED'
         GROUP BY p.id ORDER BY total_amount DESC LIMIT 10",
        [$from, $to]
    );
    ?>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-chart-bar"></i> Laporan Penjualan</h3>
            <div class="card-tools no-print">
                <a href="index.php?page=report_sales&export=csv&from=<?= e($from) ?>&to=<?= e($to) ?>" class="btn btn-sm btn-success"><i class="fas fa-file-excel"></i> Export CSV</a>
                <button class="btn btn-sm btn-secondary" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
            </div>
        </div>
        <div class="card-body">
            <form method="get" class="form-inline mb-3 no-print">
                <input type="hidden" name="page" value="report_sales">
                <label class="mr-2">Periode</label>
                <input type="date" name="from" class="form-control mr-2" value="<?= e($from) ?>">
                <span class="mr-2">s/d</span>
                <input type="date" name="to" class="form-control mr-2" value="<?= e($to) ?>">
                <button class="btn btn-primary"><i class="fas fa-search"></i> Tampilkan</button>
            </form>
            <h6 class="text-muted">Periode: <?= fdate($from) ?> - <?= fdate($to) ?></h6>

            <table class="table table-bordered table-striped">
                <thead class="thead-light"><tr><th>No. SO</th><th>Tanggal</th><th>Customer</th><th>Status</th><th class="text-right">Total</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= e($r['so_number']) ?></td>
                        <td><?= fdate($r['order_date']) ?></td>
                        <td><?= e($r['customer_name']) ?></td>
                        <td><?= statusBadge($r['status']) ?></td>
                        <td class="text-right"><?= money($r['total']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot><tr><td colspan="4" class="text-right font-weight-bold">GRAND TOTAL</td>
                    <td class="text-right font-weight-bold text-primary"><?= money($grandTotal) ?></td></tr></tfoot>
            </table>

            <h5 class="mt-4"><i class="fas fa-trophy"></i> Produk Terlaris</h5>
            <table class="table table-bordered">
                <thead class="thead-light"><tr><th>Kode</th><th>Produk</th><th class="text-right">Qty Terjual</th><th class="text-right">Nilai Penjualan</th></tr></thead>
                <tbody>
                <?php foreach ($topProducts as $tp): ?>
                    <tr>
                        <td><?= e($tp['code']) ?></td>
                        <td><?= e($tp['name']) ?></td>
                        <td class="text-right"><?= number_format($tp['total_qty']) ?></td>
                        <td class="text-right"><?= money($tp['total_amount']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}
