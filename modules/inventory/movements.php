<?php
// -----------------------------------------------------
// Modul Inventory: Kartu Stok (Stock Movements)
// -----------------------------------------------------

$pageTitle = 'Kartu Stok';

function module_handle(): void
{
    // read-only
}

function module_render(): void
{
    $products = Database::all('SELECT id, code, name FROM products ORDER BY code');
    $productFilter = (int)($_GET['product_id'] ?? 0);
    $typeFilter = $_GET['type'] ?? '';

    $sql = "SELECT m.*, p.code, p.name, u.full_name AS creator
            FROM stock_movements m
            JOIN products p ON p.id = m.product_id
            LEFT JOIN users u ON u.id = m.created_by WHERE 1=1";
    $params = [];
    if ($productFilter > 0) { $sql .= " AND m.product_id = ?"; $params[] = $productFilter; }
    if (in_array($typeFilter, ['IN','OUT'], true)) { $sql .= " AND m.movement_type = ?"; $params[] = $typeFilter; }
    $sql .= " ORDER BY m.created_at DESC, m.id DESC LIMIT 500";
    $movements = Database::all($sql, $params);
    ?>
    <div class="card">
        <div class="card-header"><h3 class="card-title"><i class="fas fa-exchange-alt"></i> Riwayat Pergerakan Stok</h3></div>
        <div class="card-body">
            <form method="get" class="form-inline mb-3">
                <input type="hidden" name="page" value="movements">
                <select name="product_id" class="form-control mr-2">
                    <option value="0">Semua Produk</option>
                    <?php foreach ($products as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $productFilter === (int)$p['id'] ? 'selected' : '' ?>>
                            <?= e($p['code']) ?> - <?= e($p['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="type" class="form-control mr-2">
                    <option value="">Semua Tipe</option>
                    <option value="IN" <?= $typeFilter === 'IN' ? 'selected' : '' ?>>Masuk (IN)</option>
                    <option value="OUT" <?= $typeFilter === 'OUT' ? 'selected' : '' ?>>Keluar (OUT)</option>
                </select>
                <button class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
            </form>
            <table class="table table-bordered table-striped datatable">
                <thead>
                    <tr><th>Tanggal</th><th>Produk</th><th>Tipe</th><th class="text-right">Qty</th><th>Referensi</th><th>Keterangan</th><th>Oleh</th></tr>
                </thead>
                <tbody>
                <?php foreach ($movements as $m): ?>
                    <tr>
                        <td><?= date('d/m/Y H:i', strtotime($m['created_at'])) ?></td>
                        <td><?= e($m['code']) ?> - <?= e($m['name']) ?></td>
                        <td><?= statusBadge($m['movement_type']) ?></td>
                        <td class="text-right"><?= number_format($m['qty']) ?></td>
                        <td>
                            <?= e($m['reference_type']) ?>
                            <?php if ($m['reference_id']): ?>
                                <?php $link = $m['reference_type'] === 'PURCHASE' ? 'purchase_view' : ($m['reference_type'] === 'SALES' ? 'sales_view' : null); ?>
                                <?php if ($link): ?>#<a href="index.php?page=<?= $link ?>&id=<?= $m['reference_id'] ?>"><?= $m['reference_id'] ?></a>
                                <?php else: ?>#<?= $m['reference_id'] ?><?php endif; ?>
                            <?php else: ?>-<?php endif; ?>
                        </td>
                        <td><?= e($m['notes']) ?></td>
                        <td><?= e($m['creator'] ?? '-') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}
