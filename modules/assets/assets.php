<?php
// -----------------------------------------------------
// Modul Assets: Fixed Asset Register
// -----------------------------------------------------

$pageTitle = 'Aset Tetap';

function module_handle(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $data = [
            trim($_POST['code']), trim($_POST['name']), trim($_POST['category'] ?? ''),
            $_POST['purchase_date'] ?: null, (float)$_POST['purchase_value'],
            (float)$_POST['salvage_value'], (int)$_POST['useful_life_years'],
            $_POST['status'], trim($_POST['location'] ?? ''),
        ];
        if ($id > 0) {
            Database::query(
                'UPDATE assets SET code=?, name=?, category=?, purchase_date=?, purchase_value=?, salvage_value=?, useful_life_years=?, status=?, location=? WHERE id=?',
                [...$data, $id]
            );
        } else {
            Database::query(
                'INSERT INTO assets (code, name, category, purchase_date, purchase_value, salvage_value, useful_life_years, status, location) VALUES (?,?,?,?,?,?,?,?,?)',
                $data
            );
        }
        logActivity('assets', 'SAVE_ASSET', "Aset {$data[1]} disimpan");
        setFlash('success', 'Aset disimpan.');
    } elseif ($action === 'delete') {
        Database::query('DELETE FROM assets WHERE id = ?', [(int)$_POST['id']]);
        setFlash('success', 'Aset dihapus.');
    }
    redirect('index.php?page=assets');
}

function module_render(): void
{
    $items = Database::all(
        'SELECT a.*,
            (SELECT COALESCE(SUM(amount),0) FROM asset_depreciations WHERE asset_id = a.id AND posted = 1) AS total_depr
         FROM assets a ORDER BY a.code'
    );
    $totalValue = 0;
    $totalDepr = 0;
    foreach ($items as $a) {
        $totalValue += $a['purchase_value'];
        $totalDepr += $a['total_depr'];
    }
    ?>
    <div class="row mb-3">
        <div class="col-md-4">
            <div class="info-box bg-primary"><span class="info-box-icon"><i class="fas fa-building"></i></span>
                <div class="info-box-content"><span class="info-box-text">Total Nilai Aset</span><span class="info-box-number"><?= money($totalValue) ?></span></div></div>
        </div>
        <div class="col-md-4">
            <div class="info-box bg-warning"><span class="info-box-icon"><i class="fas fa-chart-line"></i></span>
                <div class="info-box-content"><span class="info-box-text">Akumulasi Penyusutan</span><span class="info-box-number"><?= money($totalDepr) ?></span></div></div>
        </div>
        <div class="col-md-4">
            <div class="info-box bg-success"><span class="info-box-icon"><i class="fas fa-coins"></i></span>
                <div class="info-box-content"><span class="info-box-text">Nilai Buku</span><span class="info-box-number"><?= money($totalValue - $totalDepr) ?></span></div></div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Daftar Aset Tetap</h3>
            <div class="card-tools">
                <a href="index.php?page=depreciation" class="btn btn-warning btn-sm"><i class="fas fa-calculator"></i> Hitung Penyusutan</a>
                <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#assetModal" onclick="resetAsset()"><i class="fas fa-plus"></i> Tambah Aset</button>
            </div>
        </div>
        <div class="card-body">
            <table class="table table-bordered table-striped datatable">
                <thead>
                    <tr>
                        <th>Kode</th><th>Nama Aset</th><th>Kategori</th><th>Tgl Beli</th>
                        <th class="text-right">Nilai Beli</th><th class="text-right">Penyusutan</th>
                        <th class="text-right">Nilai Buku</th><th>Umur</th><th>Status</th><th width="100">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($items as $a):
                    $bookValue = $a['purchase_value'] - $a['total_depr'];
                ?>
                    <tr>
                        <td><?= e($a['code']) ?></td>
                        <td><?= e($a['name']) ?></td>
                        <td><?= e($a['category']) ?></td>
                        <td><?= fdate($a['purchase_date']) ?></td>
                        <td class="text-right"><?= money($a['purchase_value']) ?></td>
                        <td class="text-right text-danger"><?= money($a['total_depr']) ?></td>
                        <td class="text-right font-weight-bold"><?= money($bookValue) ?></td>
                        <td><?= $a['useful_life_years'] ?> thn</td>
                        <td><?= statusBadge($a['status']) ?></td>
                        <td>
                            <button class="btn btn-sm btn-warning" data-toggle="modal" data-target="#assetModal" onclick='editAsset(<?= json_encode($a, JSON_HEX_APOS) ?>)'><i class="fas fa-edit"></i></button>
                            <form method="post" class="d-inline" onsubmit="return confirm('Hapus aset ini?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $a['id'] ?>">
                                <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="assetModal">
        <div class="modal-dialog modal-lg">
            <form method="post" class="modal-content">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="a_id" value="0">
                <div class="modal-header"><h5 class="modal-title">Form Aset</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group"><label>Kode</label><input type="text" name="code" id="a_code" class="form-control" required></div>
                            <div class="form-group"><label>Nama Aset</label><input type="text" name="name" id="a_name" class="form-control" required></div>
                            <div class="form-group"><label>Kategori</label><input type="text" name="category" id="a_cat" class="form-control" placeholder="cth: Kendaraan, Mesin, IT"></div>
                            <div class="form-group"><label>Tanggal Beli</label><input type="date" name="purchase_date" id="a_date" class="form-control"></div>
                            <div class="form-group"><label>Lokasi</label><input type="text" name="location" id="a_loc" class="form-control"></div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group"><label>Nilai Beli</label><input type="number" name="purchase_value" id="a_value" class="form-control" min="0" step="any" value="0" required></div>
                            <div class="form-group"><label>Nilai Sisa (Salvage)</label><input type="number" name="salvage_value" id="a_salvage" class="form-control" min="0" step="any" value="0"></div>
                            <div class="form-group"><label>Umur Ekonomis (tahun)</label><input type="number" name="useful_life_years" id="a_life" class="form-control" min="1" value="4" required></div>
                            <div class="form-group">
                                <label>Status</label>
                                <select name="status" id="a_status" class="form-control">
                                    <option value="ACTIVE">ACTIVE</option>
                                    <option value="DISPOSED">DISPOSED</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Metode penyusutan: <b>Straight Line</b> (garis lurus) — (Nilai Beli - Nilai Sisa) / Umur Ekonomis / 12 per bulan.
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

function module_scripts(): void
{
    ?>
<script>
function resetAsset() {
    $('#a_id').val(0);
    $('#a_code, #a_name, #a_cat, #a_loc').val('');
    $('#a_date').val('');
    $('#a_value, #a_salvage').val(0);
    $('#a_life').val(4);
    $('#a_status').val('ACTIVE');
}
function editAsset(a) {
    $('#a_id').val(a.id);
    $('#a_code').val(a.code);
    $('#a_name').val(a.name);
    $('#a_cat').val(a.category);
    $('#a_date').val(a.purchase_date);
    $('#a_value').val(a.purchase_value);
    $('#a_salvage').val(a.salvage_value);
    $('#a_life').val(a.useful_life_years);
    $('#a_status').val(a.status);
    $('#a_loc').val(a.location);
}
</script>
    <?php
}
