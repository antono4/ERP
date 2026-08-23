<?php
// -----------------------------------------------------
// Modul Currency: Mata Uang & Kurs
// -----------------------------------------------------

$pageTitle = 'Mata Uang';

function module_handle(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $data = [trim($_POST['code']), trim($_POST['name']), trim($_POST['symbol'] ?? ''), (float)$_POST['exchange_rate']];
        if ($id > 0) {
            Database::query('UPDATE currencies SET code=?, name=?, symbol=?, exchange_rate=? WHERE id=?', [...$data, $id]);
        } else {
            Database::query('INSERT INTO currencies (code, name, symbol, exchange_rate) VALUES (?,?,?,?)', $data);
        }
        setFlash('success', 'Mata uang disimpan.');
    } elseif ($action === 'set_base') {
        $id = (int)$_POST['id'];
        Database::query('UPDATE currencies SET is_base = 0');
        Database::query('UPDATE currencies SET is_base = 1 WHERE id = ?', [$id]);
        setFlash('success', 'Mata uang dasar diubah.');
    }
    redirect('index.php?page=currencies');
}

function module_render(): void
{
    $items = Database::all('SELECT * FROM currencies ORDER BY is_base DESC, code');
    $edit = isset($_GET['edit']) ? Database::row('SELECT * FROM currencies WHERE id = ?', [(int)$_GET['edit']]) : null;
    ?>
    <div class="row">
        <div class="col-md-4">
            <div class="card card-primary">
                <div class="card-header"><h3 class="card-title"><?= $edit ? 'Edit' : 'Tambah' ?> Mata Uang</h3></div>
                <form method="post">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" value="<?= $edit['id'] ?? 0 ?>">
                    <div class="card-body">
                        <div class="form-group"><label>Kode (ISO)</label><input type="text" name="code" class="form-control" required value="<?= e($edit['code'] ?? '') ?>" placeholder="cth: USD"></div>
                        <div class="form-group"><label>Nama</label><input type="text" name="name" class="form-control" required value="<?= e($edit['name'] ?? '') ?>"></div>
                        <div class="form-group"><label>Simbol</label><input type="text" name="symbol" class="form-control" value="<?= e($edit['symbol'] ?? '') ?>" placeholder="cth: $"></div>
                        <div class="form-group"><label>Kurs ke IDR</label><input type="number" name="exchange_rate" class="form-control" min="0" step="0.0001" value="<?= $edit['exchange_rate'] ?? 1 ?>"></div>
                        <small class="text-muted">Kurs terhadap mata uang dasar (IDR).</small>
                    </div>
                    <div class="card-footer">
                        <button class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Daftar Mata Uang</h3></div>
                <div class="card-body">
                    <table class="table table-bordered table-striped datatable">
                        <thead><tr><th>Kode</th><th>Nama</th><th>Simbol</th><th class="text-right">Kurs ke IDR</th><th>Status</th><th width="100">Aksi</th></tr></thead>
                        <tbody>
                        <?php foreach ($items as $c): ?>
                            <tr>
                                <td><?= e($c['code']) ?></td>
                                <td><?= e($c['name']) ?></td>
                                <td><?= e($c['symbol'] ?? '-') ?></td>
                                <td class="text-right"><?= number_format($c['exchange_rate'], 2) ?></td>
                                <td><?= $c['is_base'] ? '<span class="badge badge-primary">Base</span>' : '<span class="badge badge-secondary">Foreign</span>' ?></td>
                                <td>
                                    <a href="index.php?page=currencies&edit=<?= $c['id'] ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
                                    <?php if (!$c['is_base']): ?>
                                        <form method="post" class="d-inline" onsubmit="return confirm('Jadikan mata uang dasar?')">
                                            <input type="hidden" name="action" value="set_base">
                                            <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                            <button class="btn btn-sm btn-info"><i class="fas fa-star"></i></button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php
}
