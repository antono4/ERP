<?php
// -----------------------------------------------------
// Modul System: Company Settings
// -----------------------------------------------------

$pageTitle = 'Pengaturan Perusahaan';

function module_handle(): void
{
    Auth::requireRole(['admin']);
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

    Database::query(
        'UPDATE company_settings SET company_name=?, address=?, phone=?, email=?, tax_id=?, currency=?, date_format=? WHERE id = 1',
        [
            trim($_POST['company_name']), trim($_POST['address'] ?? ''),
            trim($_POST['phone'] ?? ''), trim($_POST['email'] ?? ''),
            trim($_POST['tax_id'] ?? ''), trim($_POST['currency'] ?? 'Rp'),
            trim($_POST['date_format'] ?? 'd/m/Y'),
        ]
    );
    logActivity('system', 'UPDATE_SETTINGS', 'Pengaturan perusahaan diupdate');
    setFlash('success', 'Pengaturan disimpan.');
    redirect('index.php?page=settings');
}

function module_render(): void
{
    Auth::requireRole(['admin']);
    $s = Database::row('SELECT * FROM company_settings WHERE id = 1');
    ?>
    <div class="row">
        <div class="col-md-8">
            <div class="card card-primary">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-building"></i> Profil Perusahaan</h3></div>
                <form method="post">
                    <div class="card-body">
                        <div class="form-group"><label>Nama Perusahaan</label><input type="text" name="company_name" class="form-control" value="<?= e($s['company_name']) ?>" required></div>
                        <div class="form-group"><label>Alamat</label><textarea name="address" class="form-control" rows="3"><?= e($s['address']) ?></textarea></div>
                        <div class="row">
                            <div class="col-md-6"><div class="form-group"><label>Telepon</label><input type="text" name="phone" class="form-control" value="<?= e($s['phone']) ?>"></div></div>
                            <div class="col-md-6"><div class="form-group"><label>Email</label><input type="email" name="email" class="form-control" value="<?= e($s['email']) ?>"></div></div>
                        </div>
                        <div class="row">
                            <div class="col-md-6"><div class="form-group"><label>NPWP</label><input type="text" name="tax_id" class="form-control" value="<?= e($s['tax_id']) ?>"></div></div>
                            <div class="col-md-3"><div class="form-group"><label>Mata Uang</label><input type="text" name="currency" class="form-control" value="<?= e($s['currency']) ?>"></div></div>
                            <div class="col-md-3"><div class="form-group"><label>Format Tanggal</label><input type="text" name="date_format" class="form-control" value="<?= e($s['date_format']) ?>"></div></div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button class="btn btn-primary"><i class="fas fa-save"></i> Simpan Pengaturan</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-info">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-info-circle"></i> Informasi Sistem</h3></div>
                <div class="card-body">
                    <table class="table table-borderless table-sm">
                        <tr><td class="text-muted">Aplikasi</td><td><?= APP_NAME ?> v<?= APP_VERSION ?></td></tr>
                        <tr><td class="text-muted">PHP</td><td><?= PHP_VERSION ?></td></tr>
                        <tr><td class="text-muted">Database</td><td><?= DB_NAME ?></td></tr>
                        <tr><td class="text-muted">Total User</td><td><?= Database::value('SELECT COUNT(*) FROM users') ?></td></tr>
                        <tr><td class="text-muted">Total Produk</td><td><?= Database::value('SELECT COUNT(*) FROM products') ?></td></tr>
                        <tr><td class="text-muted">Total Karyawan</td><td><?= Database::value('SELECT COUNT(*) FROM employees') ?></td></tr>
                        <tr><td class="text-muted">Total Project</td><td><?= Database::value('SELECT COUNT(*) FROM projects') ?></td></tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php
}
