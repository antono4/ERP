<?php
// -----------------------------------------------------
// Modul QC: Form Buat Inspeksi
// -----------------------------------------------------

$pageTitle = 'Buat Inspeksi QC';

function module_handle(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

    $refType = $_POST['reference_type'];
    $refId = (int)$_POST['reference_id'];
    $date = $_POST['inspection_date'];
    $inspectorId = (int)$_POST['inspector_id'] ?: null;
    $productIds = $_POST['product_id'] ?? [];
    $qtys = $_POST['qty_checked'] ?? [];

    $validItems = [];
    foreach ($productIds as $i => $pid) {
        $pid = (int)$pid;
        $qty = (float)($qtys[$i] ?? 0);
        if ($pid > 0 && $qty > 0) {
            $validItems[] = ['product_id' => $pid, 'qty' => $qty];
        }
    }

    if (empty($validItems)) {
        setFlash('danger', 'Minimal satu item harus diisi.');
        redirect('index.php?page=qc_form');
    }

    Database::begin();
    try {
        $qcNo = generateNumber('qc_inspections', 'inspection_number', 'QC');
        Database::query(
            'INSERT INTO qc_inspections (inspection_number, reference_type, reference_id, inspection_date, inspector_id, status, created_by) VALUES (?,?,?,?,?,\'PENDING\',?)',
            [$qcNo, $refType, $refId, $date, $inspectorId, Auth::user()['id']]
        );
        $qcId = (int)Database::lastId();
        foreach ($validItems as $it) {
            Database::query(
                'INSERT INTO qc_inspection_items (inspection_id, product_id, qty_checked) VALUES (?,?,?)',
                [$qcId, $it['product_id'], $it['qty']]
            );
        }
        Database::commit();
        logActivity('qc', 'CREATE_QC', "Inspeksi {$qcNo} dibuat");
        setFlash('success', "Inspeksi {$qcNo} dibuat.");
        redirect('index.php?page=qc');
    } catch (Exception $ex) {
        Database::rollback();
        setFlash('danger', 'Gagal: ' . $ex->getMessage());
        redirect('index.php?page=qc_form');
    }
}

function module_render(): void
{
    $employees = Database::all("SELECT * FROM employees WHERE status='ACTIVE' ORDER BY full_name");
    $products = Database::all("SELECT * FROM products WHERE status = 1 ORDER BY name");
    ?>
    <div class="card card-primary">
        <div class="card-header"><h3 class="card-title"><i class="fas fa-clipboard-check"></i> Form Inspeksi QC</h3></div>
        <form method="post">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Tipe Referensi</label>
                            <select name="reference_type" class="form-control" required>
                                <option value="PURCHASE">Pembelian (PO)</option>
                                <option value="SALES">Penjualan (SO)</option>
                                <option value="PRODUCTION">Produksi (WO)</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group"><label>ID Referensi</label><input type="number" name="reference_id" class="form-control" min="1" required></div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group"><label>Tanggal Inspeksi</label><input type="date" name="inspection_date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Inspektor</label>
                            <select name="inspector_id" class="form-control select2">
                                <option value="">- Pilih -</option>
                                <?php foreach ($employees as $e): ?>
                                    <option value="<?= $e['id'] ?>"><?= e($e['full_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <h5 class="mt-3"><i class="fas fa-list"></i> Item yang Dicek</h5>
                <table class="table table-bordered" id="qcTable">
                    <thead class="thead-light"><tr><th width="70%">Produk</th><th width="20%">Qty Dicek</th><th width="10%"></th></tr></thead>
                    <tbody>
                        <tr class="qc-row">
                            <td>
                                <select name="product_id[]" class="form-control" required>
                                    <option value="">- Pilih -</option>
                                    <?php foreach ($products as $p): ?>
                                        <option value="<?= $p['id'] ?>"><?= e($p['code']) ?> - <?= e($p['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input type="number" name="qty_checked[]" class="form-control" min="1" step="any" value="1" required></td>
                            <td><button type="button" class="btn btn-danger btn-sm remove-qc-row"><i class="fas fa-times"></i></button></td>
                        </tr>
                    </tbody>
                </table>
                <button type="button" class="btn btn-success btn-sm" id="addQcRow"><i class="fas fa-plus"></i> Tambah Baris</button>
            </div>
            <div class="card-footer">
                <button class="btn btn-primary"><i class="fas fa-save"></i> Buat Inspeksi</button>
                <a href="index.php?page=qc" class="btn btn-secondary">Batal</a>
            </div>
        </form>
    </div>
    <?php
}

function module_scripts(): void
{
    ?>
<script>
$(function () {
    $('#addQcRow').click(function () {
        var row = $('.qc-row:first').clone();
        row.find('select').val('');
        row.find('input').val(1);
        $('#qcTable tbody').append(row);
    });
    $('#qcTable').on('click', '.remove-qc-row', function () {
        if ($('.qc-row').length > 1) $(this).closest('tr').remove();
    });
});
</script>
    <?php
}
