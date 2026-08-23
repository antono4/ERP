<?php
// -----------------------------------------------------
// Modul CRM: Opportunities (Sales Pipeline)
// -----------------------------------------------------

$pageTitle = 'Opportunities';

function module_handle(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $data = [
            trim($_POST['opp_number'] ?? generateNumber('opportunities','opp_number','OPP')),
            (int)$_POST['lead_id'] ?: null, (int)$_POST['customer_id'] ?: null,
            trim($_POST['name']), $_POST['stage'],
            (int)$_POST['probability'], (float)$_POST['expected_value'],
            $_POST['expected_close_date'] ?: null, (int)$_POST['assigned_to'] ?: null,
            trim($_POST['notes'] ?? ''), Auth::user()['id']
        ];
        if ($id > 0) {
            Database::query(
                'UPDATE opportunities SET opp_number=?, lead_id=?, customer_id=?, name=?, stage=?, probability=?, expected_value=?, expected_close_date=?, assigned_to=?, notes=? WHERE id=?',
                [...array_slice($data, 0, -1), $id]
            );
        } else {
            Database::query(
                'INSERT INTO opportunities (opp_number, lead_id, customer_id, name, stage, probability, expected_value, expected_close_date, assigned_to, notes, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                $data
            );
        }
        logActivity('crm', 'SAVE_OPP', "Opportunity {$data[0]} disimpan");
        setFlash('success', 'Opportunity disimpan.');
    }
    redirect('index.php?page=opportunities');
}

function module_render(): void
{
    $items = Database::all(
        'SELECT o.*, c.name AS customer_name, l.company_name AS lead_name, e.full_name AS assigned_name
         FROM opportunities o
         LEFT JOIN customers c ON c.id = o.customer_id
         LEFT JOIN leads l ON l.id = o.lead_id
         LEFT JOIN employees e ON e.id = o.assigned_to
         ORDER BY o.expected_close_date'
    );
    $leads = Database::all('SELECT * FROM leads WHERE status != "CONVERTED" ORDER BY company_name');
    $customers = Database::all('SELECT * FROM customers ORDER BY name');
    $employees = Database::all("SELECT * FROM employees WHERE status='ACTIVE' ORDER BY full_name");

    // Pipeline summary
    $pipeline = Database::all(
        'SELECT stage, COUNT(*) AS count, SUM(expected_value) AS total_value
         FROM opportunities WHERE stage NOT IN ("CLOSED_WON","CLOSED_LOST")
         GROUP BY stage'
    );
    $totalPipeline = array_sum(array_column($pipeline, 'total_value'));
    ?>
    <div class="row mb-3">
        <?php
        $stages = ['PROSPECTING','QUALIFICATION','PROPOSAL','NEGOTIATION'];
        $colors = ['info','primary','warning','success'];
        foreach ($stages as $i => $st):
            $s = array_filter($pipeline, fn($p) => $p['stage'] === $st);
            $s = reset($s) ?: ['count' => 0, 'total_value' => 0];
        ?>
        <div class="col-md-3">
            <div class="info-box bg-<?= $colors[$i] ?>">
                <span class="info-box-icon"><i class="fas fa-funnel-dollar"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text"><?= $st ?></span>
                    <span class="info-box-number"><?= $s['count'] ?> opp</span>
                    <small><?= money($s['total_value']) ?></small>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Daftar Opportunities</h3>
            <div class="card-tools">
                <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#oppModal" onclick="resetOpp()"><i class="fas fa-plus"></i> Tambah Opportunity</button>
            </div>
        </div>
        <div class="card-body">
            <table class="table table-bordered table-striped datatable">
                <thead><tr><th>No. OPP</th><th>Nama</th><th>Lead/Customer</th><th>Stage</th><th class="text-right">Prob.</th><th class="text-right">Nilai</th><th>Close Date</th><th>Assigned</th><th width="80">Aksi</th></tr></thead>
                <tbody>
                <?php foreach ($items as $o): ?>
                    <tr>
                        <td><?= e($o['opp_number']) ?></td>
                        <td><?= e($o['name']) ?></td>
                        <td><?= e($o['customer_name'] ?: $o['lead_name'] ?? '-') ?></td>
                        <td><?= statusBadge($o['stage']) ?></td>
                        <td class="text-right"><?= $o['probability'] ?>%</td>
                        <td class="text-right"><?= money($o['expected_value']) ?></td>
                        <td><?= fdate($o['expected_close_date']) ?></td>
                        <td><?= e($o['assigned_name'] ?? '-') ?></td>
                        <td><button class="btn btn-sm btn-warning" data-toggle="modal" data-target="#oppModal" onclick='editOpp(<?= json_encode($o, JSON_HEX_APOS) ?>)'><i class="fas fa-edit"></i></button></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="oppModal">
        <div class="modal-dialog modal-lg">
            <form method="post" class="modal-content">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="o_id" value="0">
                <div class="modal-header"><h5 class="modal-title">Form Opportunity</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group"><label>Nama Opportunity</label><input type="text" name="name" id="o_name" class="form-control" required></div>
                            <div class="form-group">
                                <label>Lead</label>
                                <select name="lead_id" id="o_lead" class="form-control select2">
                                    <option value="">- Pilih -</option>
                                    <?php foreach ($leads as $l): ?><option value="<?= $l['id'] ?>"><?= e($l['lead_number']) ?> - <?= e($l['company_name']) ?></option><?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Customer (jika sudah jadi customer)</label>
                                <select name="customer_id" id="o_cust" class="form-control select2">
                                    <option value="">- Pilih -</option>
                                    <?php foreach ($customers as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Stage</label>
                                <select name="stage" id="o_stage" class="form-control">
                                    <?php foreach (['PROSPECTING','QUALIFICATION','PROPOSAL','NEGOTIATION','CLOSED_WON','CLOSED_LOST'] as $s): ?>
                                        <option value="<?= $s ?>"><?= $s ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group"><label>Probabilitas (%)</label><input type="number" name="probability" id="o_prob" class="form-control" min="0" max="100" value="10"></div>
                            <div class="form-group"><label>Nilai Estimasi</label><input type="number" name="expected_value" id="o_value" class="form-control" min="0" step="any" value="0"></div>
                            <div class="form-group"><label>Expected Close Date</label><input type="date" name="expected_close_date" id="o_close" class="form-control"></div>
                        </div>
                    </div>
                    <div class="form-group"><label>Catatan</label><textarea name="notes" id="o_notes" class="form-control" rows="2"></textarea></div>
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
function resetOpp() {
    $('#o_id').val(0);
    $('#o_name, #o_notes').val('');
    $('#o_lead, #o_cust').val('').trigger('change');
    $('#o_stage').val('PROSPECTING');
    $('#o_prob').val(10);
    $('#o_value').val(0);
    $('#o_close').val('');
}
function editOpp(o) {
    $('#o_id').val(o.id);
    $('#o_name').val(o.name);
    $('#o_lead').val(o.lead_id).trigger('change');
    $('#o_cust').val(o.customer_id).trigger('change');
    $('#o_stage').val(o.stage);
    $('#o_prob').val(o.probability);
    $('#o_value').val(o.expected_value);
    $('#o_close').val(o.expected_close_date);
    $('#o_notes').val(o.notes);
}
</script>
    <?php
}
