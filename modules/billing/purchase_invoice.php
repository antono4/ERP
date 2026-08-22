<?php
// -----------------------------------------------------
// Modul Billing: Faktur Pembelian (Purchase Invoice / AP)
// -----------------------------------------------------

$pageTitle = 'Faktur Pembelian';

function module_handle(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $poId = (int)$_POST['po_id'];
        $po = Database::row('SELECT p.*, s.id AS sid FROM purchase_orders p JOIN suppliers s ON s.id = p.supplier_id WHERE p.id = ?', [$poId]);
        if (!$po) {
            setFlash('danger', 'Purchase Order tidak ditemukan.');
            redirect('index.php?page=purchase_invoice');
        }
        $existing = Database::row('SELECT id FROM purchase_invoices WHERE po_id = ?', [$poId]);
        if ($existing) {
            setFlash('warning', 'Invoice untuk PO ini sudah ada.');
            redirect('index.php?page=purchase_invoice');
        }
        $dueDate = date('Y-m-d', strtotime($_POST['invoice_date'] . ' +30 days'));
        $invNo = generateNumber('purchase_invoices', 'invoice_number', 'PI');
        Database::query(
            'INSERT INTO purchase_invoices (invoice_number, po_id, supplier_id, invoice_date, due_date, total, status, created_by) VALUES (?,?,?,?,?,?,\'UNPAID\',?)',
            [$invNo, $poId, $po['sid'], $_POST['invoice_date'], $dueDate, $po['total'], Auth::user()['id']]
        );
        logActivity('billing', 'CREATE_INVOICE', "Faktur pembelian {$invNo} untuk PO {$po['po_number']}");
        setFlash('success', "Faktur {$invNo} berhasil dibuat.");
        redirect('index.php?page=purchase_invoice');
    }
}

function module_render(): void
{
    Database::query("UPDATE purchase_invoices SET status='OVERDUE' WHERE status IN ('UNPAID','PARTIAL') AND due_date < CURDATE()");

    $filter = $_GET['filter'] ?? '';
    $sql = "SELECT pi.*, s.name AS supplier_name, po.po_number
            FROM purchase_invoices pi
            JOIN suppliers s ON s.id = pi.supplier_id
            JOIN purchase_orders po ON po.id = pi.po_id WHERE 1=1";
    $params = [];
    if ($filter === 'overdue') { $sql .= " AND pi.status = 'OVERDUE'"; }
    if ($filter === 'unpaid')  { $sql .= " AND pi.status IN ('UNPAID','OVERDUE')"; }
    if ($filter === 'paid')    { $sql .= " AND pi.status = 'PAID'"; }
    $sql .= " ORDER BY pi.invoice_date DESC, pi.id DESC";
    $invoices = Database::all($sql, $params);

    $poWithoutInvoice = Database::all(
        "SELECT p.id, p.po_number, p.order_date, s.name AS supplier_name, p.total
         FROM purchase_orders p JOIN suppliers s ON s.id = p.supplier_id
         WHERE p.status = 'RECEIVED' AND p.id NOT IN (SELECT po_id FROM purchase_invoices)
         ORDER BY p.order_date DESC"
    );

    $summary = Database::row(
        "SELECT
            COALESCE(SUM(CASE WHEN status = 'UNPAID' THEN total END),0) AS total_unpaid,
            COALESCE(SUM(CASE WHEN status = 'OVERDUE' THEN total END),0) AS total_overdue,
            COALESCE(SUM(CASE WHEN status = 'PARTIAL' THEN total - paid END),0) AS total_partial,
            COALESCE(SUM(CASE WHEN status = 'PAID' THEN total END),0) AS total_paid
         FROM purchase_invoices"
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

    <?php if (!empty($poWithoutInvoice)): ?>
    <div class="card card-warning collapsed-card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-exclamation-triangle"></i> PO Received Belum Dibuatkan Invoice (<?= count($poWithoutInvoice) ?>)</h3>
            <div class="card-tools"><button class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-plus"></i></button></div>
        </div>
        <div class="card-body p-0">
            <table class="table table-sm">
                <thead><tr><th>No. PO</th><th>Tanggal</th><th>Supplier</th><th class="text-right">Total</th><th width="100">Aksi</th></tr></thead>
                <tbody>
                <?php foreach ($poWithoutInvoice as $po): ?>
                    <tr>
                        <td><?= e($po['po_number']) ?></td>
                        <td><?= fdate($po['order_date']) ?></td>
                        <td><?= e($po['supplier_name']) ?></td>
                        <td class="text-right"><?= money($po['total']) ?></td>
                        <td>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="action" value="create">
                                <input type="hidden" name="po_id" value="<?= $po['id'] ?>">
                                <input type="hidden" name="invoice_date" value="<?= date('Y-m-d') ?>">
                                <button class="btn btn-sm btn-primary" onclick="return confirm('Buat invoice untuk PO ini?')"><i class="fas fa-file-invoice"></i> Buat Invoice</button>
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
            <h3 class="card-title">Daftar Faktur Pembelian</h3>
            <div class="card-tools">
                <a href="index.php?page=purchase_invoice" class="btn btn-sm <?= $filter === '' ? 'btn-dark' : 'btn-outline-dark' ?>">Semua</a>
                <a href="index.php?page=purchase_invoice&filter=unpaid" class="btn btn-sm <?= $filter === 'unpaid' ? 'btn-dark' : 'btn-outline-dark' ?>">Belum Lunas</a>
                <a href="index.php?page=purchase_invoice&filter=overdue" class="btn btn-sm <?= $filter === 'overdue' ? 'btn-dark' : 'btn-outline-dark' ?>">Jatuh Tempo</a>
                <a href="index.php?page=purchase_invoice&filter=paid" class="btn btn-sm <?= $filter === 'paid' ? 'btn-dark' : 'btn-outline-dark' ?>">Lunas</a>
            </div>
        </div>
        <div class="card-body">
            <table class="table table-bordered table-striped datatable">
                <thead>
                    <tr>
                        <th>No. Invoice</th><th>No. PO</th><th>Supplier</th><th>Tgl Invoice</th><th>Jatuh Tempo</th>
                        <th class="text-right">Total</th><th class="text-right">Terbayar</th><th class="text-right">Sisa</th><th>Status</th><th width="80">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($invoices as $inv): ?>
                    <tr>
                        <td><?= e($inv['invoice_number']) ?></td>
                        <td><?= e($inv['po_number']) ?></td>
                        <td><?= e($inv['supplier_name']) ?></td>
                        <td><?= fdate($inv['invoice_date']) ?></td>
                        <td><?= fdate($inv['due_date']) ?></td>
                        <td class="text-right"><?= money($inv['total']) ?></td>
                        <td class="text-right text-success"><?= money($inv['paid']) ?></td>
                        <td class="text-right font-weight-bold <?= $inv['total'] - $inv['paid'] > 0 ? 'text-danger' : 'text-success' ?>">
                            <?= money($inv['total'] - $inv['paid']) ?>
                        </td>
                        <td><?= statusBadge($inv['status']) ?></td>
                        <td><a href="index.php?page=payments&invoice_type=PURCHASE&invoice_id=<?= $inv['id'] ?>" class="btn btn-sm btn-success" title="Bayar"><i class="fas fa-money-bill-wave"></i></a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}
