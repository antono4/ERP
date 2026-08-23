<?php
// -----------------------------------------------------
// Modul CRM: Leads
// -----------------------------------------------------

$pageTitle = 'Leads';

function module_handle(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $data = [
            trim($_POST['lead_number'] ?? generateNumber('leads','lead_number','LED')),
            trim($_POST['company_name']), trim($_POST['contact_name'] ?? ''),
            trim($_POST['email'] ?? ''), trim($_POST['phone'] ?? ''),
            trim($_POST['source'] ?? ''), $_POST['status'],
            (float)$_POST['estimated_value'], (int)$_POST['assigned_to'] ?: null,
            trim($_POST['notes'] ?? ''), Auth::user()['id']
        ];
        if ($id > 0) {
            Database::query(
                'UPDATE leads SET lead_number=?, company_name=?, contact_name=?, email=?, phone=?, source=?, status=?, estimated_value=?, assigned_to=?, notes=? WHERE id=?',
                [...array_slice($data, 0, -1), $id]
            );
        } else {
            Database::query(
                'INSERT INTO leads (lead_number, company_name, contact_name, email, phone, source, status, estimated_value, assigned_to, notes, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                $data
            );
        }
        logActivity('crm', 'SAVE_LEAD', "Lead {$data[0]} disimpan");
        setFlash('success', 'Lead disimpan.');
    } elseif ($action === 'convert') {
        $id = (int)$_POST['id'];
        $lead = Database::row('SELECT * FROM leads WHERE id = ?', [$id]);
        if ($lead && $lead['status'] !== 'CONVERTED') {
            Database::begin();
            try {
                $custCode = generateNumber('customers', 'code', 'CUST');
                Database::query(
                    'INSERT INTO customers (code, name, email, phone, address) VALUES (?,?,?,?,?)',
                    [$custCode, $lead['company_name'], $lead['email'], $lead['phone'], '']
                );
                $custId = (int)Database::lastId();
                Database::query("UPDATE leads SET status='CONVERTED' WHERE id=?", [$id]);
                Database::commit();
                logActivity('crm', 'CONVERT_LEAD', "Lead {$lead['lead_number']} dikonversi ke customer {$custCode}");
                setFlash('success', "Lead dikonversi ke customer {$custCode}.");
            } catch (Exception $ex) {
                Database::rollback();
                setFlash('danger', 'Gagal: ' . $ex->getMessage());
            }
        }
    }
    redirect('index.php?page=leads');
}

function module_render(): void
{
    $items = Database::all(
        'SELECT l.*, e.full_name AS assigned_name
         FROM leads l LEFT JOIN employees e ON e.id = l.assigned_to ORDER BY l.created_at DESC'
    );
    $employees = Database::all("SELECT * FROM employees WHERE status='ACTIVE' ORDER BY full_name");
    $edit = isset($_GET['edit']) ? Database::row('SELECT * FROM leads WHERE id = ?', [(int)$_GET['edit']]) : null;
    ?>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Daftar Leads</h3>
            <div class="card-tools">
                <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#leadModal" onclick="resetLead()"><i class="fas fa-plus"></i> Tambah Lead</button>
            </div>
        </div>
        <div class="card-body">
            <table class="table table-bordered table-striped datatable">
                <thead><tr><th>No. Lead</th><th>Perusahaan</th><th>Kontak</th><th>Email</th><th>Sumber</th><th class="text-right">Est. Nilai</th><th>Status</th><th>Assigned</th><th width="120">Aksi</th></tr></thead>
                <tbody>
                <?php foreach ($items as $l): ?>
                    <tr>
                        <td><?= e($l['lead_number']) ?></td>
                        <td><?= e($l['company_name']) ?></td>
                        <td><?= e($l['contact_name'] ?? '-') ?></td>
                        <td><?= e($l['email'] ?? '-') ?></td>
                        <td><?= e($l['source'] ?? '-') ?></td>
                        <td class="text-right"><?= money($l['estimated_value']) ?></td>
                        <td><?= statusBadge($l['status']) ?></td>
                        <td><?= e($l['assigned_name'] ?? '-') ?></td>
                        <td>
                            <button class="btn btn-sm btn-warning" data-toggle="modal" data-target="#leadModal" onclick='editLead(<?= json_encode($l, JSON_HEX_APOS) ?>)'><i class="fas fa-edit"></i></button>
                            <?php if ($l['status'] !== 'CONVERTED'): ?>
                                <form method="post" class="d-inline" onsubmit="return confirm('Konversi lead ke customer?')">
                                    <input type="hidden" name="action" value="convert">
                                    <input type="hidden" name="id" value="<?= $l['id'] ?>">
                                    <button class="btn btn-sm btn-success"><i class="fas fa-exchange-alt"></i></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="leadModal">
        <div class="modal-dialog modal-lg">
            <form method="post" class="modal-content">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="l_id" value="0">
                <div class="modal-header"><h5 class="modal-title">Form Lead</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group"><label>Nama Perusahaan</label><input type="text" name="company_name" id="l_company" class="form-control" required></div>
                            <div class="form-group"><label>Nama Kontak</label><input type="text" name="contact_name" id="l_contact" class="form-control"></div>
                            <div class="form-group"><label>Email</label><input type="email" name="email" id="l_email" class="form-control"></div>
                            <div class="form-group"><label>Telepon</label><input type="text" name="phone" id="l_phone" class="form-control"></div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group"><label>Sumber</label><input type="text" name="source" id="l_source" class="form-control" placeholder="cth: Website, Referral"></div>
                            <div class="form-group"><label>Estimasi Nilai</label><input type="number" name="estimated_value" id="l_value" class="form-control" min="0" step="any" value="0"></div>
                            <div class="form-group">
                                <label>Assigned To</label>
                                <select name="assigned_to" id="l_assigned" class="form-control select2">
                                    <option value="">- Pilih -</option>
                                    <?php foreach ($employees as $e): ?><option value="<?= $e['id'] ?>"><?= e($e['full_name']) ?></option><?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Status</label>
                                <select name="status" id="l_status" class="form-control">
                                    <?php foreach (['NEW','CONTACTED','QUALIFIED','CONVERTED','LOST'] as $s): ?><option value="<?= $s ?>"><?= $s ?></option><?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="form-group"><label>Catatan</label><textarea name="notes" id="l_notes" class="form-control" rows="2"></textarea></div>
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
function resetLead() {
    $('#l_id').val(0);
    $('#l_company, #l_contact, #l_email, #l_phone, #l_source, #l_notes').val('');
    $('#l_value').val(0);
    $('#l_assigned').val('').trigger('change');
    $('#l_status').val('NEW');
}
function editLead(l) {
    $('#l_id').val(l.id);
    $('#l_company').val(l.company_name);
    $('#l_contact').val(l.contact_name);
    $('#l_email').val(l.email);
    $('#l_phone').val(l.phone);
    $('#l_source').val(l.source);
    $('#l_value').val(l.estimated_value);
    $('#l_assigned').val(l.assigned_to).trigger('change');
    $('#l_status').val(l.status);
    $('#l_notes').val(l.notes);
}
</script>
    <?php
}
