<?php
// -----------------------------------------------------
// Modul HR: Payroll Detail (Slip Gaji)
// -----------------------------------------------------

function module_handle(): void
{
    $id = (int)($_GET['id'] ?? 0);
    $p = Database::row('SELECT payroll_number FROM payrolls WHERE id = ?', [$id]);
    $GLOBALS['pageTitle'] = $p ? 'Detail ' . $p['payroll_number'] : 'Payroll';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_item') {
        $itemId = (int)$_POST['item_id'];
        $allowances = (float)$_POST['allowances'];
        $deductions = (float)$_POST['deductions'];
        $item = Database::row('SELECT * FROM payroll_items WHERE id = ?', [$itemId]);
        if ($item) {
            $net = $item['base_salary'] + $allowances - $deductions;
            Database::query('UPDATE payroll_items SET allowances=?, deductions=?, net_salary=? WHERE id=?', [$allowances, $deductions, $net, $itemId]);
            Database::query('UPDATE payrolls SET total = (SELECT SUM(net_salary) FROM payroll_items WHERE payroll_id = ?) WHERE id = ?', [$item['payroll_id'], $item['payroll_id']]);
            setFlash('success', 'Item payroll diupdate.');
        }
        redirect('index.php?page=payroll_view&id=' . $item['payroll_id']);
    }
}

function module_render(): void
{
    $id = (int)($_GET['id'] ?? 0);
    $payroll = Database::row('SELECT * FROM payrolls WHERE id = ?', [$id]);
    if (!$payroll) {
        setFlash('danger', 'Payroll tidak ditemukan.');
        redirect('index.php?page=payroll');
    }
    $GLOBALS['pageTitle'] = 'Detail ' . $payroll['payroll_number'];
    $items = Database::all(
        'SELECT pi.*, e.full_name, e.employee_number, d.name AS dept_name
         FROM payroll_items pi
         JOIN employees e ON e.id = pi.employee_id
         LEFT JOIN departments d ON d.id = e.department_id
         WHERE pi.payroll_id = ? ORDER BY e.full_name',
        [$id]
    );
    $editable = in_array($payroll['status'], ['DRAFT'], true);
    $canApprove = in_array(Auth::user()['role'], ['admin','manager'], true);
    ?>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-money-check-alt"></i> <?= e($payroll['payroll_number']) ?> — Periode <?= e($payroll['period']) ?> <?= statusBadge($payroll['status']) ?>
            </h3>
            <div class="card-tools">
                <a href="index.php?page=payroll" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
                <?php if ($payroll['status'] === 'DRAFT' && $canApprove): ?>
                    <form method="post" action="index.php?page=payroll" class="d-inline" onsubmit="return confirm('Setujui payroll ini?')">
                        <input type="hidden" name="id" value="<?= $payroll['id'] ?>">
                        <button name="action" value="approve" class="btn btn-sm btn-warning"><i class="fas fa-check"></i> Approve</button>
                    </form>
                <?php endif; ?>
                <?php if ($payroll['status'] === 'APPROVED'): ?>
                    <form method="post" action="index.php?page=payroll" class="d-inline" onsubmit="return confirm('Bayar payroll ini? Jurnal gaji akan dibuat.')">
                        <input type="hidden" name="id" value="<?= $payroll['id'] ?>">
                        <button name="action" value="pay" class="btn btn-sm btn-success"><i class="fas fa-money-bill-wave"></i> Bayar</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body">
            <table class="table table-bordered table-striped">
                <thead class="thead-light">
                    <tr>
                        <th>NIK</th><th>Nama</th><th>Departemen</th>
                        <th class="text-right">Gaji Pokok</th>
                        <th class="text-right">Tunjangan</th>
                        <th class="text-right">Potongan</th>
                        <th class="text-right">Gaji Bersih</th>
                        <th>Catatan</th>
                        <?php if ($editable): ?><th width="80">Aksi</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($items as $it): ?>
                    <tr>
                        <td><?= e($it['employee_number']) ?></td>
                        <td><?= e($it['full_name']) ?></td>
                        <td><?= e($it['dept_name'] ?? '-') ?></td>
                        <td class="text-right"><?= money($it['base_salary']) ?></td>
                        <td class="text-right text-success"><?= money($it['allowances']) ?></td>
                        <td class="text-right text-danger"><?= money($it['deductions']) ?></td>
                        <td class="text-right font-weight-bold"><?= money($it['net_salary']) ?></td>
                        <td><small><?= e($it['notes']) ?></small></td>
                        <?php if ($editable): ?>
                        <td><button class="btn btn-sm btn-warning" data-toggle="modal" data-target="#editItem<?= $it['id'] ?>"><i class="fas fa-edit"></i></button></td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" class="text-right font-weight-bold">TOTAL</td>
                        <td class="text-right font-weight-bold"><?= money(array_sum(array_column($items,'base_salary'))) ?></td>
                        <td class="text-right font-weight-bold text-success"><?= money(array_sum(array_column($items,'allowances'))) ?></td>
                        <td class="text-right font-weight-bold text-danger"><?= money(array_sum(array_column($items,'deductions'))) ?></td>
                        <td class="text-right font-weight-bold text-primary"><?= money($payroll['total']) ?></td>
                        <td></td>
                        <?php if ($editable): ?><td></td><?php endif; ?>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <?php foreach ($items as $it): ?>
    <div class="modal fade" id="editItem<?= $it['id'] ?>">
        <div class="modal-dialog">
            <form method="post" class="modal-content">
                <input type="hidden" name="action" value="update_item">
                <input type="hidden" name="item_id" value="<?= $it['id'] ?>">
                <div class="modal-header"><h5 class="modal-title">Edit: <?= e($it['full_name']) ?></h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
                <div class="modal-body">
                    <p>Gaji Pokok: <b><?= money($it['base_salary']) ?></b></p>
                    <div class="form-group"><label>Tunjangan</label><input type="number" name="allowances" class="form-control" min="0" step="any" value="<?= $it['allowances'] ?>"></div>
                    <div class="form-group"><label>Potongan</label><input type="number" name="deductions" class="form-control" min="0" step="any" value="<?= $it['deductions'] ?>"></div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                </div>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
    <?php
}
