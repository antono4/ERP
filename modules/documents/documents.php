<?php
// -----------------------------------------------------
// Modul Documents: Document Management
// -----------------------------------------------------

$pageTitle = 'Dokumen';

function module_handle(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $title = trim($_POST['title']);
        $category = trim($_POST['category'] ?? '');
        $relatedType = trim($_POST['related_type'] ?? '');
        $relatedId = (int)($_POST['related_id'] ?? 0);

        if (isset($_FILES['doc_file']) && $_FILES['doc_file']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = BASE_PATH . '/uploads/documents/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $filename = uniqid() . '_' . basename($_FILES['doc_file']['name']);
            $filePath = $uploadDir . $filename;
            if (move_uploaded_file($_FILES['doc_file']['tmp_name'], $filePath)) {
                $docNo = generateNumber('documents', 'doc_number', 'DOC');
                Database::query(
                    'INSERT INTO documents (doc_number, title, category, related_type, related_id, file_path, file_size, file_type, uploaded_by) VALUES (?,?,?,?,?,?,?,?,?)',
                    [$docNo, $title, $category, $relatedType, $relatedId, 'uploads/documents/' . $filename, $_FILES['doc_file']['size'], $_FILES['doc_file']['type'], Auth::user()['id']]
                );
                logActivity('documents', 'UPLOAD', "Dokumen {$docNo}: {$title}");
                setFlash('success', "Dokumen {$docNo} diupload.");
            } else {
                setFlash('danger', 'Gagal upload file.');
            }
        } else {
            setFlash('danger', 'Pilih file untuk diupload.');
        }
        redirect('index.php?page=documents');
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $doc = Database::row('SELECT * FROM documents WHERE id = ?', [$id]);
        if ($doc) {
            $file = BASE_PATH . '/' . $doc['file_path'];
            if (file_exists($file)) unlink($file);
            Database::query('DELETE FROM documents WHERE id = ?', [$id]);
            setFlash('success', 'Dokumen dihapus.');
        }
        redirect('index.php?page=documents');
    }
}

function module_render(): void
{
    $items = Database::all(
        'SELECT d.*, u.full_name AS uploader FROM documents d LEFT JOIN users u ON u.id = d.uploaded_by ORDER BY d.created_at DESC'
    );
    ?>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Daftar Dokumen</h3>
            <div class="card-tools">
                <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#docModal"><i class="fas fa-upload"></i> Upload Dokumen</button>
            </div>
        </div>
        <div class="card-body">
            <table class="table table-bordered table-striped datatable">
                <thead><tr><th>No. Dok</th><th>Judul</th><th>Kategori</th><th>Referensi</th><th class="text-right">Size</th><th>Oleh</th><th width="100">Aksi</th></tr></thead>
                <tbody>
                <?php foreach ($items as $d): ?>
                    <tr>
                        <td><?= e($d['doc_number']) ?></td>
                        <td><?= e($d['title']) ?></td>
                        <td><?= e($d['category'] ?? '-') ?></td>
                        <td><?= e($d['related_type'] ?? '-') ?> <?= $d['related_id'] ? '#' . $d['related_id'] : '' ?></td>
                        <td class="text-right"><?= number_format($d['file_size'] / 1024, 1) ?> KB</td>
                        <td><?= e($d['uploader'] ?? '-') ?></td>
                        <td>
                            <a href="<?= e($d['file_path']) ?>" target="_blank" class="btn btn-sm btn-info"><i class="fas fa-download"></i></a>
                            <form method="post" class="d-inline" onsubmit="return confirm('Hapus dokumen?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $d['id'] ?>">
                                <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="docModal">
        <div class="modal-dialog">
            <form method="post" enctype="multipart/form-data" class="modal-content">
                <input type="hidden" name="action" value="save">
                <div class="modal-header"><h5 class="modal-title">Upload Dokumen</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
                <div class="modal-body">
                    <div class="form-group"><label>Judul Dokumen</label><input type="text" name="title" class="form-control" required></div>
                    <div class="form-group"><label>Kategori</label><input type="text" name="category" class="form-control" placeholder="cth: Kontrak, Invoice, Bukti Bayar"></div>
                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                <label>Tipe Referensi</label>
                                <select name="related_type" class="form-control">
                                    <option value="">- Tidak ada -</option>
                                    <option value="PURCHASE">Purchase Order</option>
                                    <option value="SALES">Sales Order</option>
                                    <option value="PROJECT">Project</option>
                                    <option value="CUSTOMER">Customer</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group"><label>ID Referensi</label><input type="number" name="related_id" class="form-control" min="1"></div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>File</label>
                        <input type="file" name="doc_file" class="form-control-file" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary"><i class="fas fa-upload"></i> Upload</button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                </div>
            </form>
        </div>
    </div>
    <?php
}
