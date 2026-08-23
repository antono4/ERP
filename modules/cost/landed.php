<?php
// -----------------------------------------------------
// Modul Landed Cost: Biaya Kirim/Bea Masuk
// -----------------------------------------------------

$pageTitle = 'Landed Cost';

function module_handle(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $poId = (int)$_POST['po_id'];
        $costType = $_POST['cost_type'];
        $amount = (float)$_POST['amount'];
        $method = $_POST['allocation_method'];

        Database::begin();
        try {
            $lcNo = generateNumber('landed_costs', 'lc_number', 'LC');
            Database::query(
                'INSERT INTO landed_costs (lc_number, po_id, cost_type, amount, allocation_method, status, created_by) VALUES (?,?,?,?,?,\'DRAFT\',?)',
                [$lcNo, $poId, $costType, $amount, $method, Auth::user()['id']]
            );
            Database::commit();
            logActivity('cost', 'CREATE_LC', "Landed cost {$lcNo} untuk PO #{$poId}");
            setFlash('success', "Landed cost {$lcNo} dibuat.");
        } catch (Exception $ex) {
            Database::rollback();
            setFlash('danger', 'Gagal: ' . $ex->getMessage());
        }
        redirect('index.php?page=cost');
    }

    if ($action === 'allocate') {
        $id = (int)$_POST['id'];
        $lc = Database::row('SELECT * FROM landed_costs WHERE id = ?', [$id]);
        if (!$lc || $lc['status'] !== 'DRAFT') redirect('index.php?page=cost');

        $po = Database::row('SELECT * FROM purchase_orders WHERE id = ?', [$lc['po_id']]);
        $items = Database::all(
            'SELECT pi.*, p.name FROM purchase_order_items pi JOIN products p ON p.id = pi.product_id WHERE pi.po_id = ?',
            [$lc['po_id']]
        );

        if ($lc['allocation_method'] === 'VALUE') {
            $totalValue = $po['total'];
            foreach ($items as $it) {
                $alloc = $lc['amount'] * ($it['subtotal'] / $totalValue);
                Database::query(
                    'INSERT INTO landed_cost_allocations (lc_id, product_id, allocated_amount) VALUES (?,?,?)',
                    [$id, $it['product_id'], round($alloc)]
                );
            }
        } elseif ($lc['allocation_method'] === 'QTY') {
            $totalQty = array_sum(array_column($items, 'qty'));
            foreach ($items as $it) {
                $alloc = $lc['amount'] * ($it['qty'] / $totalQty);
                Database::query(
                    'INSERT INTO landed_cost_allocations (lc_id, product_id, allocated_amount) VALUES (?,?,?)',
                    [$id, $it['product_id'], round($alloc)]
                );
            }
        }
        Database::query("UPDATE landed_costs SET status='ALLOCATED' WHERE id=?", [$id]);
        setFlash('success', 'Landed cost dialokasikan.');
        redirect('index.php?page=cost');
    }
}

function module_render(): void
{
    $items = Database::all(
        'SELECT lc.*, po.po_number, u.full_name AS creator
         FROM landed_costs lc
         JOIN purchase_orders po ON po.id = lc.po_id
         LEFT JOIN users u ON u.id = lc.created_by ORDER BY lc.created_at DESC'
    );
    $receivedPOs = Database::all(
        "SELECT p.id, p.po_number, s.name AS supplier_name, p.total
         FROM purchase_orders p JOIN suppliers s ON s.id = p.supplier_id
         WHERE p.status = 'RECEIVED' ORDER BY p.order_date DESC"
    );
    ?>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Daftar Landed Cost</h3>
            <div class="card-tools">
                <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#lcModal"><i class="fas fa-plus"></i> Buat Landed Cost</button>
            </div>
        </div>
        <div class="card-body">
            <table class="table table-bordered table-striped datatable">
                <thead><tr><th>No. LC</th><th>No. PO</th><th>Tipe</th><th class="text-right">Jumlah</th><th>Metode</th><th>Status</th><th width="100">Aksi</th></tr></thead>
                <tbody>
                <?php foreach ($items as $lc): ?>
                    <tr>
                        <td><?= e($lc['lc_number']) ?></td>
                        <td><?= e($lc['po_number']) ?></td>
                        <td><?= statusBadge($lc['cost_type']) ?></td>
                        <td class="text-right"><?= money($lc['amount']) ?></td>
                        <td><?= e($lc['allocation_method']) ?></td>
                        <td><?= statusBadge($lc['status']) ?></td>
                        <td>
                            <?php if ($lc['status'] === 'DRAFT'): ?>
                                <form method="post" class="d-inline" onsubmit="return confirm('Alokasikan biaya?')">
                                    <input type="hidden" name="action" value="allocate">
                                    <input type="hidden" name="id" value="<?= $lc['id'] ?>">
                                    <button class="btn btn-sm btn-success"><i class="fas fa-check"></i></button>
                                </form>
                            <?php elseif ($lc['status'] === 'ALLOCATED'): ?>
                                <button class="btn btn-sm btn-info" data-toggle="modal" data-target="#allocModal<?= $lc['id'] ?>"><i class="fas fa-eye"></i></button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php foreach ($items as $lc):
        if ($lc['status'] !== 'ALLOCATED') continue;
        $allocs = Database::all(
            'SELECT a.*, p.code, p.name FROM landed_cost_allocations a JOIN products p ON p.id = a.product_id WHERE a.lc_id = ?',
            [$lc['id']]
        );
    ?>
    <div class="modal fade" id="allocModal<?= $lc['id'] ?>">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Alokasi: <?= e($lc['lc_number']) ?></h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
                <div class="modal-body">
                    <table class="table table-sm table-bordered">
                        <thead class="thead-light"><tr><th>Produk</th><th class="text-right">Alokasi</th></tr></thead>
                        <tbody>
                        <?php foreach ($allocs as $a): ?>
                            <tr><td><?= e($a['code']) ?> - <?= e($a['name']) ?></td><td class="text-right"><?= money($a['allocated_amount']) ?></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot><tr><td class="text-right font-weight-bold">TOTAL</td><td class="text-right font-weight-bold"><?= money(array_sum(array_column($allocs,'allocated_amount'))) ?></td></tr></tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <div class="modal fade" id="lcModal">
        <div class="modal-dialog">
            <form method="post" class="modal-content">
                <input type="hidden" name="action" value="create">
                <div class="modal-header"><h5 class="modal-title">Buat Landed Cost</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Purchase Order (RECEIVED)</label>
                        <select name="po_id" class="form-control select2" required>
                            <option value="">- Pilih -</option>
                            <?php foreach ($receivedPOs as $po): ?>
                                <option value="<?= $po['id'] ?>"><?= e($po['po_number']) ?> - <?= e($po['supplier_name']) ?> (<?= money($po['total']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Tipe Biaya</label>
                        <select name="cost_type" class="form-control">
                            <?php foreach (['FREIGHT','INSURANCE','CUSTOMS','HANDLING','OTHER'] as $t): ?>
                                <option value="<?= $t ?>"><?= $t ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>Jumlah</label><input type="number" name="amount" class="form-control" min="0.01" step="any" required></div>
                    <div class="form-group">
                        <label>Metode Alokasi</label>
                        <select name="allocation_method" class="form-control">
                            <option value="VALUE">by Value (nilai PO)</option>
                            <option value="QTY">by Quantity</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                </div>
            </form>
        </div>
    </div>
    <?php
}
