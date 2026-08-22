<?php
// -----------------------------------------------------
// Modul HR: Karyawan
// -----------------------------------------------------

$pageTitle = 'Karyawan';

function module_handle(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $data = [
            trim($_POST['employee_number']), trim($_POST['full_name']),
            (int)$_POST['department_id'] ?: null, (int)$_POST['position_id'] ?: null,
            trim($_POST['email'] ?? ''), trim($_POST['phone'] ?? ''),
            $_POST['hire_date'] ?: null, (float)$_POST['base_salary'],
            trim($_POST['bank_account'] ?? ''), $_POST['status'],
        ];
        if ($id > 0) {
            Database::query(
                'UPDATE employees SET employee_number=?, full_name=?, department_id=?, position_id=?, email=?, phone=?, hire_date=?, base_salary=?, bank_account=?, status=? WHERE id=?',
                [...$data, $id]
            );
        } else {
            Database::query(
                'INSERT INTO employees (employee_number, full_name, department_id, position_id, email, phone, hire_date, base_salary, bank_account, status) VALUES (?,?,?,?,?,?,?,?,?,?)',
                $data
            );
        }
        logActivity('hr', 'SAVE_EMPLOYEE', "Karyawan {$data[1]} disimpan");
        setFlash('success', 'Data karyawan disimpan.');
    } elseif ($action === 'delete') {
        Database::query('DELETE FROM employees WHERE id = ?', [(int)$_POST['id']]);
        setFlash('success', 'Karyawan dihapus.');
    }
    redirect('index.php?page=employees');
}

function module_render(): void
{
    $items = Database::all(
        'SELECT e.*, d.name AS dept_name, p.name AS pos_name
         FROM employees e
         LEFT JOIN departments d ON d.id = e.department_id
         LEFT JOIN positions p ON p.id = e.position_id
         ORDER BY e.employee_number'
    );
    $departments = Database::all('SELECT * FROM departments ORDER BY name');
    $positions = Database::all('SELECT * FROM positions ORDER BY name');
    ?>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Daftar Karyawan</h3>
            <div class="card-tools">
                <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#empModal" onclick="resetEmp()"><i class="fas fa-plus"></i> Tambah Karyawan</button>
            </div>
        </div>
        <div class="card-body">
            <table class="table table-bordered table-striped datatable">
                <thead>
                    <tr><th>NIK</th><th>Nama</th><th>Departemen</th><th>Jabatan</th><th>Email</th><th class="text-right">Gaji Pokok</th><th>Status</th><th width="100">Aksi</th></tr>
                </thead>
                <tbody>
                <?php foreach ($items as $e): ?>
                    <tr>
                        <td><?= e($e['employee_number']) ?></td>
                        <td><?= e($e['full_name']) ?></td>
                        <td><?= e($e['dept_name'] ?? '-') ?></td>
                        <td><?= e($e['pos_name'] ?? '-') ?></td>
                        <td><?= e($e['email']) ?></td>
                        <td class="text-right"><?= money($e['base_salary']) ?></td>
                        <td><?= statusBadge($e['status']) ?></td>
                        <td>
                            <button class="btn btn-sm btn-warning" data-toggle="modal" data-target="#empModal" onclick='editEmp(<?= json_encode($e, JSON_HEX_APOS) ?>)'><i class="fas fa-edit"></i></button>
                            <form method="post" class="d-inline" onsubmit="return confirm('Hapus karyawan ini?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $e['id'] ?>">
                                <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="empModal">
        <div class="modal-dialog modal-lg">
            <form method="post" class="modal-content">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="e_id" value="0">
                <div class="modal-header"><h5 class="modal-title">Form Karyawan</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group"><label>NIK</label><input type="text" name="employee_number" id="e_nik" class="form-control" required></div>
                            <div class="form-group"><label>Nama Lengkap</label><input type="text" name="full_name" id="e_name" class="form-control" required></div>
                            <div class="form-group">
                                <label>Departemen</label>
                                <select name="department_id" id="e_dept" class="form-control select2">
                                    <option value="">- Pilih -</option>
                                    <?php foreach ($departments as $d): ?><option value="<?= $d['id'] ?>"><?= e($d['name']) ?></option><?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Jabatan</label>
                                <select name="position_id" id="e_pos" class="form-control select2">
                                    <option value="">- Pilih -</option>
                                    <?php foreach ($positions as $p): ?><option value="<?= $p['id'] ?>"><?= e($p['name']) ?></option><?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group"><label>Email</label><input type="email" name="email" id="e_email" class="form-control"></div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group"><label>Telepon</label><input type="text" name="phone" id="e_phone" class="form-control"></div>
                            <div class="form-group"><label>Tanggal Masuk</label><input type="date" name="hire_date" id="e_hire" class="form-control"></div>
                            <div class="form-group"><label>Gaji Pokok</label><input type="number" name="base_salary" id="e_salary" class="form-control" min="0" step="any" value="0"></div>
                            <div class="form-group"><label>No. Rekening</label><input type="text" name="bank_account" id="e_bank" class="form-control"></div>
                            <div class="form-group">
                                <label>Status</label>
                                <select name="status" id="e_status" class="form-control">
                                    <option value="ACTIVE">ACTIVE</option>
                                    <option value="SUSPENDED">SUSPENDED</option>
                                    <option value="RESIGNED">RESIGNED</option>
                                </select>
                            </div>
                        </div>
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
function resetEmp() {
    $('#e_id').val(0);
    $('#e_nik, #e_name, #e_email, #e_phone, #e_bank').val('');
    $('#e_dept, #e_pos').val('').trigger('change');
    $('#e_hire').val('');
    $('#e_salary').val(0);
    $('#e_status').val('ACTIVE');
}
function editEmp(e) {
    $('#e_id').val(e.id);
    $('#e_nik').val(e.employee_number);
    $('#e_name').val(e.full_name);
    $('#e_dept').val(e.department_id).trigger('change');
    $('#e_pos').val(e.position_id).trigger('change');
    $('#e_email').val(e.email);
    $('#e_phone').val(e.phone);
    $('#e_hire').val(e.hire_date);
    $('#e_salary').val(e.base_salary);
    $('#e_bank').val(e.bank_account);
    $('#e_status').val(e.status);
}
</script>
    <?php
}
