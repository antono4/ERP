<?php
// -----------------------------------------------------
// Modul Service: Knowledge Base
// -----------------------------------------------------

$pageTitle = 'Knowledge Base';

function module_handle(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $data = [trim($_POST['title']), trim($_POST['category'] ?? ''), trim($_POST['content'] ?? ''), isset($_POST['status']) ? 1 : 0, Auth::user()['id']];
        if ($id > 0) {
            Database::query('UPDATE knowledge_base SET title=?, category=?, content=?, status=? WHERE id=?', [...array_slice($data,0,-1), $id]);
        } else {
            Database::query('INSERT INTO knowledge_base (title, category, content, status, created_by) VALUES (?,?,?,?,?)', $data);
        }
        setFlash('success', 'Artikel disimpan.');
    } elseif ($action === 'delete') {
        Database::query('DELETE FROM knowledge_base WHERE id = ?', [(int)$_POST['id']]);
        setFlash('success', 'Artikel dihapus.');
    } elseif ($action === 'view') {
        Database::query('UPDATE knowledge_base SET views = views + 1 WHERE id = ?', [(int)$_POST['id']]);
        redirect('index.php?page=kb&view=' . (int)$_POST['id']);
    }
    redirect('index.php?page=kb');
}

function module_render(): void
{
    $viewId = (int)($_GET['view'] ?? 0);
    $viewArticle = $viewId > 0 ? Database::row('SELECT * FROM knowledge_base WHERE id = ?', [$viewId]) : null;
    $items = Database::all('SELECT kb.*, u.full_name AS creator FROM knowledge_base kb LEFT JOIN users u ON u.id = kb.created_by ORDER BY kb.views DESC');
    ?>
    <div class="row">
        <div class="col-md-<?= $viewArticle ? '5' : '12' ?>">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Daftar Artikel</h3>
                    <div class="card-tools">
                        <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#kbModal" onclick="resetKb()"><i class="fas fa-plus"></i> Artikel Baru</button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm">
                        <thead><tr><th>Judul</th><th>Kategori</th><th class="text-right">Views</th><th>Status</th><th width="80">Aksi</th></tr></thead>
                        <tbody>
                        <?php foreach ($items as $a): ?>
                            <tr>
                                <td><a href="index.php?page=kb&view=<?= $a['id'] ?>"><?= e($a['title']) ?></a></td>
                                <td><?= e($a['category'] ?? '-') ?></td>
                                <td class="text-right"><span class="badge badge-info"><?= $a['views'] ?></span></td>
                                <td><?= $a['status'] ? '<span class="badge badge-success">Publik</span>' : '<span class="badge badge-secondary">Draft</span>' ?></td>
                                <td>
                                    <button class="btn btn-sm btn-warning" data-toggle="modal" data-target="#kbModal" onclick='editKb(<?= json_encode($a, JSON_HEX_APOS) ?>)'><i class="fas fa-edit"></i></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php if ($viewArticle): ?>
        <div class="col-md-7">
            <div class="card card-info">
                <div class="card-header"><h3 class="card-title"><?= e($viewArticle['title']) ?></h3></div>
                <div class="card-body">
                    <p class="text-muted"><small>Kategori: <?= e($viewArticle['category'] ?? 'Umum') ?> | Views: <?= $viewArticle['views'] ?></small></p>
                    <div><?= nl2br(e($viewArticle['content'])) ?></div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="modal fade" id="kbModal">
        <div class="modal-dialog modal-lg">
            <form method="post" class="modal-content">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="k_id" value="0">
                <div class="modal-header"><h5 class="modal-title">Form Artikel</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
                <div class="modal-body">
                    <div class="form-group"><label>Judul</label><input type="text" name="title" id="k_title" class="form-control" required></div>
                    <div class="form-group"><label>Kategori</label><input type="text" name="category" id="k_cat" class="form-control" placeholder="cth: Purchasing, HR, Finance"></div>
                    <div class="form-group"><label>Konten</label><textarea name="content" id="k_content" class="form-control" rows="8" required></textarea></div>
                    <div class="form-check"><input type="checkbox" name="status" class="form-check-input" id="k_status" checked><label class="form-check-label" for="k_status">Publik</label></div>
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
function resetKb() {
    $('#k_id').val(0);
    $('#k_title, #k_cat, #k_content').val('');
    $('#k_status').prop('checked', true);
}
function editKb(a) {
    $('#k_id').val(a.id);
    $('#k_title').val(a.title);
    $('#k_cat').val(a.category);
    $('#k_content').val(a.content);
    $('#k_status').prop('checked', a.status == 1);
}
</script>
    <?php
}
