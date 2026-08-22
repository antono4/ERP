<?php
// -----------------------------------------------------
// Modul Billing: Faktur Penjualan (Sales Invoice / AR)
// -----------------------------------------------------

$pageTitle = 'Faktur Penjualan';

function module_handle(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $soId = (int)$_POST['so_id'];
        $so = Database::row('SELECT s.*, c.id AS cid FROM sales_orders s JOIN customers c ON c.id = s.customer_id WHERE s.id = ?', [$soId]);
        if (!$so) {
            setFlash('danger', 'Sales Order tidak ditemukan.');
            redirect('index.php?page=sales_invoice');
        }
        $existing = Database::row('SELECT id FROM sales_invoices WHERE so_id = ?', [$soId]);
        if ($existing) {
            setFlash('warning', 'Invoice untuk SO ini sudah ada.');
            redirect('index.php?page=sales_invoice');
        }
        $dueDate = date('Y-m-d', strtotime($_POST['invoice_date'] . ' +30 days'));
        $invNo = generateNumber('sales_invoices', 'invoice_number', 'SI');
        Database::query(
            'INSERT INTO sales_invoices (invoice_number, so_id, customer_id, invoice_date, due_date, total, status, created_by) VALUES (?,?,?,?,?,?,\'UNPAID\',?)',
            [$invNo, $soId, $so['cid'], $_POST['invoice_date'], $dueDate, $so['total'], Auth::user()['id']]
        );
        logActivity('billing', 'CREATE_INVOICE', "Faktur penjualan {$invNo} untuk SO {$so['so_number']}");
        setFlash('success', "Faktur {$invNo} berhasil dibuat.");
        redirect('index.php?page=sales_invoice');
    }
}

function module_render(): void
{
    // Update overdue status
    Database::query("UPDATE sales_invoices SET status='OVERDUE' WHERE status IN ('UNPAID','PARTIAL') AND due_date < CURDATE()");

    $filter = $_GET['filter'] ?? '';
    $sql = "SELECT si.*, c.name AS customer_name, so.so_number
            FROM sales_invoices si
            JOIN customers c ON c.id = si.customer_id
            JOIN sales_orders so ON so.id = si.so_id WHERE 1=1";
    $params = [];
    if ($filter === 'overdue') { $sql .= " AND si.status = 'OVERDUE'"; }
    if ($filter === 'unpaid')  { $sql .= " AND si.status IN ('UNPAID','OVERDUE')"; }
    if ($filter === 'paid')    { $sql .= " AND si.status = 'PAID'"; }
    $sql .= " ORDER BY si.invoice_date DESC, si.id DESC";
    $invoices = Database::all($sql, $params);

    $soWithoutInvoice = Database::all(
        "SELECT s.id, s.so_number, s.order_date, c.name AS customer_name, s.total
         FROM sales_orders s JOIN customers c ON c.id = s.customer_id
         WHERE s.status = 'DELIVERED' AND s.id NOT IN (SELECT so_id FROM sales_invoices)
         ORDER BY s.order_date DESC"
    );

    $summary = Database::row(
        "SELECT
            COALESCE(SUM(CASE WHEN status = 'UNPAID' THEN total END),0) AS total_unpaid,
            COALESCE(SUM(CASE WHEN status = 'OVERDUE' THEN total END),0) AS total_overdue,
            COALESCE(SUM(CASE WHEN status = 'PARTIAL' THEN total - paid END),0) AS total_partial,
            COALESCE(SUM(CASE WHEN status = 'PAID' THEN total END),0) AS total_paid
         FROM sales_invoices"
    );
    ?>
    <div class="row mb-3">
        <div class="col-md-3">
            <div class="info-box bg-danger"><span class="info-box-icon"><i class="fas fa-exclamation-circle"></i></span>
                <div class="info-box-content"><span class="info-box-text">Belum Dibayar</span><span class="info-box-number"><?= money($summary['total_unpaid']) ?></span></div></div>
        </div>
        <div class="col-md-3">
            <div class="info-box bg-warning"><span class="info-box-icon"><i class="fas fa-clock"></i></span>
                <div class="info-box-content"><span class="info-box-text">Jatuh Tempo</span><span class="info-box-number"><?= money($summary['total_overdue']) ?></span></div></div>
        </div>
        <div class="col-md-3">
            <div class="info-box bg-info"><span class="info-box-icon"><i class="fas fa-hourglass-half"></i></span>
                <div class="info-box-content"><span class="info-box-text">Sebagian</span><span class="info-box-number"><?= money($summary['total_partial']) ?></span></div></div>
        </div>
        <div class="col-md-3">
            <div class="info-box bg-success"><span class="info-box-icon"><i class="fas fa-check-circle"></i></span>
                <div class="info-box-content"><span class="info-box-text">Lunas</span><span class="info-box-number"><?= money($summary['total_paid']) ?></span></div></div>
        </div>
    </div>

    <?php if (!empty($soWithoutInvoice)): ?>
    <div class="card card-warning collapsed-card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-exclamation-triangle"></i> SO Delivered Belum Dibuatkan Invoice (<?= count($soWithoutInvoice) ?>)</h3>
            <div class="card-tools"><button class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-plus"></i></button></div>
        </div>
        <div class="card-body p-0">
            <table class="table table-sm">
                <thead><tr><th>No. SO</th><th>Tanggal</th><th>Customer</th><th class="text-right">Total</th><th width="100">Aksi</th></tr></thead>
                <tbody>
                <?php foreach ($soWithoutInvoice as $so): ?>
                    <tr>
                        <td><?= e($so['so_number']) ?></td>
                        <td><?= fdate($so['order_date']) ?></td>
                        <td><?= e($so['customer_name']) ?></td>
                        <td class="text-right"><?= money($so['total']) ?></td>
                        <td>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="action" value="create">
                                <input type="hidden" name="so_id" value="<?= $so['id'] ?>">
                                <input type="hidden" name="invoice_date" value="<?= date('Y-m-d') ?>">
                                <button class="btn btn-sm btn-primary" onclick="return confirm('Buat invoice untuk SO ini?')"><i class="fas fa-file-invoice"></i> Buat Invoice</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Daftar Faktur Penjualan</h3>
            <div class="card-tools">
                <a href="index.php?page=sales_invoice" class="btn btn-sm <?= $filter === '' ? 'btn-dark' : 'btn-outline-dark' ?>">Semua</a>
                <a href="index.php?page=sales_invoice&filter=unpaid" class="btn btn-sm <?= $filter === 'unpaid' ? 'btn-dark' : 'btn-outline-dark' ?>">Belum Lunas</a>
                <a href="index.php?page=sales_invoice&filter=overdue" class="btn btn-sm <?= $filter === 'overdue' ? 'btn-dark' : 'btn-outline-dark' ?>">Jatuh Tempo</a>
                <a href="index.php?page=sales_invoice&filter=paid" class="btn btn-sm <?= $filter === 'paid' ? 'btn-dark' : 'btn-outline-dark' ?>">Lunas</a>
            </div>
        </div>
        <div class="card-body">
            <table class="table table-bordered table-striped datatable">
                <thead>
                    <tr>
                        <th>No. Invoice</th><th>No. SO</th><th>Customer</th><th>Tgl Invoice</th><th>Jatuh Tempo</th>
                        <th class="text-right">Total</th><th class="text-right">Terbayar</th><th class="text-right">Sisa</th><th>Status</th><th width="80">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($invoices as $inv): ?>
                    <tr>
                        <td><?= e($inv['invoice_number']) ?></td>
                        <td><?= e($inv['so_number']) ?></td>
                        <td><?= e($inv['customer_name']) ?></td>
                        <td><?= fdate($inv['invoice_date']) ?></td>
                        <td><?= fdate($inv['due_date']) ?></td>
                        <td class="text-right"><?= money($inv['total']) ?></td>
                        <td class="text-right text-success"><?= money($inv['paid']) ?></td>
                        <td class="text-right font-weight-bold <?= $inv['total'] - $inv['paid'] > 0 ? 'text-danger' : 'text-success' ?>">
                            <?= money($inv['total'] - $inv['paid']) ?>
                        </td>
                        <td><?= statusBadge($inv['status']) ?></td>
                        <td><a href="index.php?page=payments&invoice_type=SALES&invoice_id=<?= $inv['id'] ?>" class="btn btn-sm btn-success" title="Bayar"><i class="fas fa-money-bill-wave"></i></a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}
