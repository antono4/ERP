<?php
// -----------------------------------------------------
// Modul Tax: e-Faktur Pajak
// -----------------------------------------------------

$pageTitle = 'e-Faktur Pajak';

function module_handle(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $soId = (int)$_POST['so_id'];
        $so = Database::row('SELECT s.*, c.id AS cid FROM sales_orders s JOIN customers c ON c.id = s.customer_id WHERE s.id = ?', [$soId]);
        if (!$so) redirect('index.php?page=efaktur');
        $tax = Database::row('SELECT * FROM taxes WHERE type = "PPN" LIMIT 1');
        $rate = $tax ? $tax['rate'] : 11;
        $ppn = $so['total'] * $rate / 100;
        $fakturNo = generateNumber('e_faktur', 'faktur_number', 'EF');
        Database::query(
            'INSERT INTO e_faktur (faktur_number, customer_id, faktur_date, dpp, ppn, total, status, created_by) VALUES (?,?,?,?,?,?,?,?)',
            [$fakturNo, $so['cid'], $_POST['faktur_date'], $so['total'], $ppn, $so['total'] + $ppn, 'DRAFT', Auth::user()['id']]
        );
        logActivity('tax', 'CREATE_EF', "e-Faktur {$fakturNo} untuk SO {$so['so_number']}");
        setFlash('success', "e-Faktur {$fakturNo} dibuat.");
        redirect('index.php?page=efaktur');
    }

    if ($action === 'approve') {
        $id = (int)$_POST['id'];
        Database::query("UPDATE e_faktur SET status='APPROVED' WHERE id=?", [$id]);
        setFlash('success', 'e-Faktur disetujui.');
        redirect('index.php?page=efaktur');
    }

    if ($action === 'upload') {
        $id = (int)$_POST['id'];
        Database::query("UPDATE e_faktur SET status='UPLOADED' WHERE id=?", [$id]);
        setFlash('success', 'e-Faktur diupload ke DJP Online.');
        redirect('index.php?page=efaktur');
    }
}

function module_render(): void
{
    $items = Database::all(
        'SELECT ef.*, c.name AS customer_name FROM e_faktur ef JOIN customers c ON c.id = ef.customer_id ORDER BY ef.faktur_date DESC'
    );
    $soWithoutFaktur = Database::all(
        "SELECT s.id, s.so_number, s.order_date, c.name AS customer_name, s.total
         FROM sales_orders s JOIN customers c ON c.id = s.customer_id
         WHERE s.status = 'DELIVERED' AND s.id NOT IN (SELECT id FROM e_faktur)
         ORDER BY s.order_date DESC"
    );
    ?>
    <?php if (!empty($soWithoutFaktur)): ?>
    <div class="card card-warning collapsed-card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-exclamation-triangle"></i> SO Delivered Belum e-Faktur (<?= count($soWithoutFaktur) ?>)</h3>
            <div class="card-tools"><button class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-plus"></i></button></div>
        </div>
        <div class="card-body p-0">
            <table class="table table-sm">
                <thead><tr><th>No. SO</th><th>Tanggal</th><th>Customer</th><th class="text-right">DPP</th><th width="100">Aksi</th></tr></thead>
                <tbody>
                <?php foreach ($soWithoutFaktur as $so): ?>
                    <tr>
                        <td><?= e($so['so_number']) ?></td>
                        <td><?= fdate($so['order_date']) ?></td>
                        <td><?= e($so['customer_name']) ?></td>
                        <td class="text-right"><?= money($so['total']) ?></td>
                        <td>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="action" value="create">
                                <input type="hidden" name="so_id" value="<?= $so['id'] ?>">
                                <input type="hidden" name="faktur_date" value="<?= date('Y-m-d') ?>">
                                <button class="btn btn-sm btn-primary"><i class="fas fa-file-invoice"></i> Buat</button>
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
        <div class="card-header"><h3 class="card-title">Daftar e-Faktur</h3></div>
        <div class="card-body">
            <table class="table table-bordered table-striped datatable">
                <thead><tr><th>No. Faktur</th><th>Customer</th><th>Tanggal</th><th class="text-right">DPP</th><th class="text-right">PPN</th><th class="text-right">Total</th><th>Status</th><th width="100">Aksi</th></tr></thead>
                <tbody>
                <?php foreach ($items as $ef): ?>
                    <tr>
                        <td><?= e($ef['faktur_number']) ?></td>
                        <td><?= e($ef['customer_name']) ?></td>
                        <td><?= fdate($ef['faktur_date']) ?></td>
                        <td class="text-right"><?= money($ef['dpp']) ?></td>
                        <td class="text-right"><?= money($ef['ppn']) ?></td>
                        <td class="text-right"><?= money($ef['total']) ?></td>
                        <td><?= statusBadge($ef['status']) ?></td>
                        <td>
                            <?php if ($ef['status'] === 'DRAFT'): ?>
                                <form method="post" class="d-inline" onsubmit="return confirm('Setujui?')">
                                    <input type="hidden" name="id" value="<?= $ef['id'] ?>">
                                    <button name="action" value="approve" class="btn btn-sm btn-warning"><i class="fas fa-check"></i></button>
                                </form>
                            <?php elseif ($ef['status'] === 'APPROVED'): ?>
                                <form method="post" class="d-inline" onsubmit="return confirm('Upload ke DJP?')">
                                    <input type="hidden" name="id" value="<?= $ef['id'] ?>">
                                    <button name="action" value="upload" class="btn btn-sm btn-success"><i class="fas fa-upload"></i></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}
