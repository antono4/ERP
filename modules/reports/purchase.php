<?php
// -----------------------------------------------------
// Modul Report: Laporan Pembelian
// -----------------------------------------------------

$pageTitle = 'Laporan Pembelian';

function module_handle(): void
{
    // read-only
}

function module_render(): void
{
    $from = $_GET['from'] ?? date('Y-m-01');
    $to = $_GET['to'] ?? date('Y-m-d');

    $rows = Database::all(
        "SELECT p.po_number, p.order_date, s.name AS supplier_name, p.status, p.total
         FROM purchase_orders p JOIN suppliers s ON s.id = p.supplier_id
         WHERE p.order_date BETWEEN ? AND ? AND p.status != 'CANCELLED'
         ORDER BY p.order_date",
        [$from, $to]
    );
    $grandTotal = array_sum(array_column($rows, 'total'));

    $bySupplier = Database::all(
        "SELECT s.name, COUNT(p.id) AS total_po, SUM(p.total) AS total_amount
         FROM purchase_orders p JOIN suppliers s ON s.id = p.supplier_id
         WHERE p.order_date BETWEEN ? AND ? AND p.status != 'CANCELLED'
         GROUP BY s.id ORDER BY total_amount DESC",
        [$from, $to]
    );
    ?>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-chart-bar"></i> Laporan Pembelian</h3>
            <div class="card-tools no-print">
                <button class="btn btn-sm btn-secondary" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
            </div>
        </div>
        <div class="card-body">
            <form method="get" class="form-inline mb-3 no-print">
                <input type="hidden" name="page" value="report_purchase">
                <label class="mr-2">Periode</label>
                <input type="date" name="from" class="form-control mr-2" value="<?= e($from) ?>">
                <span class="mr-2">s/d</span>
                <input type="date" name="to" class="form-control mr-2" value="<?= e($to) ?>">
                <button class="btn btn-primary"><i class="fas fa-search"></i> Tampilkan</button>
            </form>
            <h6 class="text-muted">Periode: <?= fdate($from) ?> - <?= fdate($to) ?></h6>

            <table class="table table-bordered table-striped">
                <thead class="thead-light"><tr><th>No. PO</th><th>Tanggal</th><th>Supplier</th><th>Status</th><th class="text-right">Total</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= e($r['po_number']) ?></td>
                        <td><?= fdate($r['order_date']) ?></td>
                        <td><?= e($r['supplier_name']) ?></td>
                        <td><?= statusBadge($r['status']) ?></td>
                        <td class="text-right"><?= money($r['total']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot><tr><td colspan="4" class="text-right font-weight-bold">GRAND TOTAL</td>
                    <td class="text-right font-weight-bold text-success"><?= money($grandTotal) ?></td></tr></tfoot>
            </table>

            <h5 class="mt-4"><i class="fas fa-truck"></i> Pembelian per Supplier</h5>
            <table class="table table-bordered">
                <thead class="thead-light"><tr><th>Supplier</th><th class="text-right">Jumlah PO</th><th class="text-right">Nilai Pembelian</th></tr></thead>
                <tbody>
                <?php foreach ($bySupplier as $bs): ?>
                    <tr>
                        <td><?= e($bs['name']) ?></td>
                        <td class="text-right"><?= $bs['total_po'] ?></td>
                        <td class="text-right"><?= money($bs['total_amount']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}
