<?php
// -----------------------------------------------------
// Modul Service: Helpdesk Ticket
// -----------------------------------------------------

$pageTitle = 'Helpdesk Ticket';

function module_handle(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $data = [
            trim($_POST['subject']), trim($_POST['description'] ?? ''),
            (int)$_POST['customer_id'] ?: null, $_POST['category'],
            $_POST['priority'], $_POST['status'],
            (int)$_POST['assigned_to'] ?: null, Auth::user()['id']
        ];
        if ($id > 0) {
            Database::query(
                'UPDATE tickets SET subject=?, description=?, customer_id=?, category=?, priority=?, status=?, assigned_to=? WHERE id=?',
                [...array_slice($data, 0, -1), $id]
            );
        } else {
            $ticketNo = generateNumber('tickets', 'ticket_number', 'TKT');
            Database::query(
                'INSERT INTO tickets (ticket_number, subject, description, customer_id, category, priority, status, assigned_to, created_by) VALUES (?,?,?,?,?,?,?,?,?)',
                [$ticketNo, ...$data]
            );
        }
        setFlash('success', 'Ticket disimpan.');
    } elseif ($action === 'reply') {
        $ticketId = (int)$_POST['ticket_id'];
        Database::query(
            'INSERT INTO ticket_replies (ticket_id, message, is_internal, created_by) VALUES (?,?,?,?)',
            [$ticketId, trim($_POST['message']), isset($_POST['is_internal']) ? 1 : 0, Auth::user()['id']]
        );
        setFlash('success', 'Balasan ditambahkan.');
        redirect('index.php?page=tickets&view=' . $ticketId);
    }
    redirect('index.php?page=tickets');
}

function module_render(): void
{
    $viewId = (int)($_GET['view'] ?? 0);
    $viewTicket = null;
    if ($viewId > 0) {
        $viewTicket = Database::row(
            'SELECT t.*, c.name AS customer_name, e.full_name AS assigned_name FROM tickets t
             LEFT JOIN customers c ON c.id = t.customer_id
             LEFT JOIN employees e ON e.id = t.assigned_to WHERE t.id = ?',
            [$viewId]
        );
    }

    $items = Database::all(
        'SELECT t.*, c.name AS customer_name, e.full_name AS assigned_name,
            (SELECT COUNT(*) FROM ticket_replies WHERE ticket_id = t.id) AS reply_count
         FROM tickets t
         LEFT JOIN customers c ON c.id = t.customer_id
         LEFT JOIN employees e ON e.id = t.assigned_to ORDER BY t.created_at DESC'
    );
    $customers = Database::all('SELECT * FROM customers ORDER BY name');
    $employees = Database::all("SELECT * FROM employees WHERE status='ACTIVE' ORDER BY full_name");
    ?>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Daftar Ticket</h3>
            <div class="card-tools">
                <a href="index.php?page=kb" class="btn btn-info btn-sm"><i class="fas fa-book"></i> Knowledge Base</a>
                <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#ticketModal" onclick="resetTicket()"><i class="fas fa-plus"></i> Buat Ticket</button>
            </div>
        </div>
        <div class="card-body">
            <table class="table table-bordered table-striped datatable">
                <thead><tr><th>No. Ticket</th><th>Subjek</th><th>Customer</th><th>Kategori</th><th>Prioritas</th><th>Status</th><th>Assigned</th><th class="text-right">Balasan</th><th width="80">Aksi</th></tr></thead>
                <tbody>
                <?php foreach ($items as $t): ?>
                    <tr>
                        <td><?= e($t['ticket_number']) ?></td>
                        <td><a href="index.php?page=tickets&view=<?= $t['id'] ?>"><?= e($t['subject']) ?></a></td>
                        <td><?= e($t['customer_name'] ?? '-') ?></td>
                        <td><?= statusBadge($t['category']) ?></td>
                        <td><?= statusBadge($t['priority']) ?></td>
                        <td><?= statusBadge($t['status']) ?></td>
                        <td><?= e($t['assigned_name'] ?? '-') ?></td>
                        <td class="text-right"><span class="badge badge-info"><?= $t['reply_count'] ?></span></td>
                        <td>
                            <button class="btn btn-sm btn-warning" data-toggle="modal" data-target="#ticketModal" onclick='editTicket(<?= json_encode($t, JSON_HEX_APOS) ?>)'><i class="fas fa-edit"></i></button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($viewTicket): ?>
    <div class="card card-outline card-info">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-comments"></i> <?= e($viewTicket['ticket_number']) ?> — <?= e($viewTicket['subject']) ?></h3>
            <div class="card-tools"><a href="index.php?page=tickets" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a></div>
        </div>
        <div class="card-body">
            <p><strong>Customer:</strong> <?= e($viewTicket['customer_name'] ?? '-') ?> | <strong>Status:</strong> <?= statusBadge($viewTicket['status']) ?> | <strong>Assigned:</strong> <?= e($viewTicket['assigned_name'] ?? '-') ?></p>
            <p><?= nl2br(e($viewTicket['description'])) ?></p>
            <hr>
            <?php
            $replies = Database::all(
                'SELECT r.*, u.full_name FROM ticket_replies r JOIN users u ON u.id = r.created_by WHERE r.ticket_id = ? ORDER BY r.created_at',
                [$viewId]
            );
            foreach ($replies as $r): ?>
                <div class="mb-2 p-2 <?= $r['is_internal'] ? 'bg-light border-left border-warning' : 'bg-white border-left border-primary' ?> rounded">
                    <small class="text-muted"><?= e($r['full_name']) ?> — <?= date('d/m/Y H:i', strtotime($r['created_at'])) ?> <?= $r['is_internal'] ? '<span class="badge badge-warning">Internal</span>' : '' ?></small>
                    <p class="mb-0"><?= nl2br(e($r['message'])) ?></p>
                </div>
            <?php endforeach; ?>
            <form method="post" class="mt-3">
                <input type="hidden" name="action" value="reply">
                <input type="hidden" name="ticket_id" value="<?= $viewId ?>">
                <div class="form-group">
                    <textarea name="message" class="form-control" rows="3" required placeholder="Balas..."></textarea>
                </div>
                <div class="form-check">
                    <input type="checkbox" name="is_internal" class="form-check-input" id="replyInternal">
                    <label class="form-check-label" for="replyInternal">Internal note (tidak ditampilkan ke customer)</label>
                </div>
                <button class="btn btn-primary btn-sm"><i class="fas fa-reply"></i> Balas</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <div class="modal fade" id="ticketModal">
        <div class="modal-dialog modal-lg">
            <form method="post" class="modal-content">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="t_id" value="0">
                <div class="modal-header"><h5 class="modal-title">Form Ticket</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
                <div class="modal-body">
                    <div class="form-group"><label>Subjek</label><input type="text" name="subject" id="t_subject" class="form-control" required></div>
                    <div class="form-group"><label>Deskripsi</label><textarea name="description" id="t_desc" class="form-control" rows="3"></textarea></div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Customer</label>
                                <select name="customer_id" id="t_cust" class="form-control select2">
                                    <option value="">- Pilih -</option>
                                    <?php foreach ($customers as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Kategori</label>
                                <select name="category" id="t_cat" class="form-control">
                                    <?php foreach (['TECHNICAL','BILLING','GENERAL','COMPLAINT','REQUEST'] as $c): ?>
                                        <option value="<?= $c ?>"><?= $c ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Prioritas</label>
                                <select name="priority" id="t_prio" class="form-control">
                                    <?php foreach (['LOW','MEDIUM','HIGH','URGENT'] as $p): ?>
                                        <option value="<?= $p ?>"><?= $p ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Assigned To</label>
                                <select name="assigned_to" id="t_assigned" class="form-control select2">
                                    <option value="">- Pilih -</option>
                                    <?php foreach ($employees as $e): ?><option value="<?= $e['id'] ?>"><?= e($e['full_name']) ?></option><?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Status</label>
                                <select name="status" id="t_status" class="form-control">
                                    <?php foreach (['OPEN','IN_PROGRESS','RESOLVED','CLOSED','CANCELLED'] as $s): ?>
                                        <option value="<?= $s ?>"><?= $s ?></option>
                                    <?php endforeach; ?>
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
function resetTicket() {
    $('#t_id').val(0);
    $('#t_subject, #t_desc').val('');
    $('#t_cust, #t_assigned').val('').trigger('change');
    $('#t_cat').val('GENERAL');
    $('#t_prio').val('MEDIUM');
    $('#t_status').val('OPEN');
}
function editTicket(t) {
    $('#t_id').val(t.id);
    $('#t_subject').val(t.subject);
    $('#t_desc').val(t.description);
    $('#t_cust').val(t.customer_id).trigger('change');
    $('#t_cat').val(t.category);
    $('#t_prio').val(t.priority);
    $('#t_status').val(t.status);
    $('#t_assigned').val(t.assigned_to).trigger('change');
}
</script>
    <?php
}
