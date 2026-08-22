<?php
// -----------------------------------------------------
// Modul QC: Inspeksi Kualitas
// -----------------------------------------------------

$pageTitle = 'Quality Control (QC)';

function module_handle(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int)$_POST['id'];
        $result = $_POST['result'];
        $notes = trim($_POST['notes'] ?? '');
        $itemIds = $_POST['item_id'] ?? [];
        $qtyPassed = $_POST['qty_passed'] ?? [];
        $qtyFailed = $_POST['qty_failed'] ?? [];
        $remarks = $_POST['remark'] ?? [];

        Database::begin();
        try {
            foreach ($itemIds as $i => $itemId) {
                Database::query(
                    'UPDATE qc_inspection_items SET qty_passed=?, qty_failed=?, remark=? WHERE id=?',
                    [(float)$qtyPassed[$i], (float)$qtyFailed[$i], trim($remarks[$i] ?? ''), (int)$itemId]
                );
            }
            Database::query('UPDATE qc_inspections SET result=?, notes=? WHERE id=?', [$result, $notes, $id]);
            Database::commit();
            $qc = Database::row('SELECT inspection_number FROM qc_inspections WHERE id = ?', [$id]);
            logActivity('qc', 'SAVE_QC', "Inspeksi {$qc['inspection_number']} hasil: {$result}");
            setFlash('success', 'Hasil inspeksi disimpan.');
        } catch (Exception $ex) {
            Database::rollback();
            setFlash('danger', 'Gagal: ' . $ex->getMessage());
        }
        redirect('index.php?page=qc');
    }
}

function module_render(): void
{
    $items = Database::all(
        'SELECT q.*, e.full_name AS inspector_name, u.full_name AS creator,
            (SELECT SUM(qty_checked) FROM qc_inspection_items WHERE inspection_id = q.id) AS total_checked,
            (SELECT SUM(qty_passed) FROM qc_inspection_items WHERE inspection_id = q.id) AS total_passed,
            (SELECT SUM(qty_failed) FROM qc_inspection_items WHERE inspection_id = q.id) AS total_failed
         FROM qc_inspections q
         LEFT JOIN employees e ON e.id = q.inspector_id
         LEFT JOIN users u ON u.id = q.created_by
         ORDER BY q.created_at DESC'
    );
    ?>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Daftar Inspeksi QC</h3>
            <div class="card-tools">
                <a href="index.php?page=qc_form" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Buat Inspeksi</a>
            </div>
        </div>
        <div class="card-body">
            <table class="table table-bordered table-striped datatable">
                <thead>
                    <tr>
                        <th>No. Inspeksi</th><th>Referensi</th><th>Tanggal</th><th>Inspektor</th>
                        <th class="text-right">Dicek</th><th class="text-right">Lolos</th><th class="text-right">Gagal</th>
                        <th>Hasil</th><th width="80">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($items as $q): ?>
                    <tr>
                        <td><?= e($q['inspection_number']) ?></td>
                        <td><?= e($q['reference_type']) ?> #<?= $q['reference_id'] ?></td>
                        <td><?= fdate($q['inspection_date']) ?></td>
                        <td><?= e($q['inspector_name'] ?? '-') ?></td>
                        <td class="text-right"><?= number_format($q['total_checked']) ?></td>
                        <td class="text-right text-success"><?= number_format($q['total_passed']) ?></td>
                        <td class="text-right text-danger"><?= number_format($q['total_failed']) ?></td>
                        <td><?= statusBadge($q['result']) ?></td>
                        <td>
                            <button class="btn btn-sm btn-warning" data-toggle="modal" data-target="#qcModal<?= $q['id'] ?>"><i class="fas fa-edit"></i></button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php foreach ($items as $q):
        $qcItems = Database::all(
            'SELECT qi.*, p.code, p.name FROM qc_inspection_items qi JOIN products p ON p.id = qi.product_id WHERE qi.inspection_id = ?',
            [$q['id']]
        );
    ?>
    <div class="modal fade" id="qcModal<?= $q['id'] ?>">
        <div class="modal-dialog modal-lg">
            <form method="post" class="modal-content">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= $q['id'] ?>">
                <div class="modal-header">
                    <h5 class="modal-title"><?= e($q['inspection_number']) ?> — <?= e($q['reference_type']) ?> #<?= $q['reference_id'] ?></h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <table class="table table-sm table-bordered">
                        <thead class="thead-light"><tr><th>Produk</th><th class="text-right">Dicek</th><th width="100">Lolos</th><th width="100">Gagal</th><th>Keterangan</th></tr></thead>
                        <tbody>
                        <?php foreach ($qcItems as $qi): ?>
                            <tr>
                                <td><?= e($qi['code']) ?> - <?= e($qi['name']) ?></td>
                                <td class="text-right"><?= number_format($qi['qty_checked']) ?></td>
                                <td><input type="number" name="qty_passed[]" class="form-control form-control-sm" min="0" max="<?= $qi['qty_checked'] ?>" step="any" value="<?= $qi['qty_passed'] ?>"></td>
                                <td><input type="number" name="qty_failed[]" class="form-control form-control-sm" min="0" max="<?= $qi['qty_checked'] ?>" step="any" value="<?= $qi['qty_failed'] ?>"></td>
                                <td><input type="text" name="remark[]" class="form-control form-control-sm" value="<?= e($qi['remark']) ?>"></td>
                                <input type="hidden" name="item_id[]" value="<?= $qi['id'] ?>">
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="form-group">
                        <label>Hasil Akhir</label>
                        <select name="result" class="form-control">
                            <?php foreach (['PENDING','PASSED','FAILED','PARTIAL'] as $r): ?>
                                <option value="<?= $r ?>" <?= $q['result'] === $r ? 'selected' : '' ?>><?= $r ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>Catatan</label><input type="text" name="notes" class="form-control" value="<?= e($q['notes']) ?>"></div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary"><i class="fas fa-save"></i> Simpan Hasil</button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                </div>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
    <?php
}
