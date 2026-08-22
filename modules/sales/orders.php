<?php
// -----------------------------------------------------
// Modul Sales: Daftar Sales Order
// -----------------------------------------------------

$pageTitle = 'Sales Order';

function module_handle(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $so = Database::row('SELECT * FROM sales_orders WHERE id = ?', [$id]);
    if (!$so) redirect('index.php?page=sales');

    if ($action === 'confirm' && $so['status'] === 'DRAFT') {
        Database::query("UPDATE sales_orders SET status='CONFIRMED' WHERE id=?", [$id]);
        setFlash('success', "SO {$so['so_number']} dikonfirmasi.");
    } elseif ($action === 'cancel' && in_array($so['status'], ['DRAFT','CONFIRMED'], true)) {
        Database::query("UPDATE sales_orders SET status='CANCELLED' WHERE id=?", [$id]);
        setFlash('warning', "SO {$so['so_number']} dibatalkan.");
    } elseif ($action === 'deliver' && $so['status'] === 'CONFIRMED') {
        // Pengiriman: stok keluar + kartu stok + jurnal penjualan & HPP
        $items = Database::all(
            'SELECT i.*, p.purchase_price, p.stock AS current_stock, p.name
             FROM sales_order_items i JOIN products p ON p.id = i.product_id WHERE i.so_id = ?',
            [$id]
        );

        // Validasi stok cukup
        foreach ($items as $it) {
            if ($it['current_stock'] < $it['qty']) {
                setFlash('danger', "Stok {$it['name']} tidak cukup (sisa {$it['current_stock']}).");
                redirect('index.php?page=sales_view&id=' . $id);
            }
        }

        Database::begin();
        try {
            $totalCogs = 0;
            foreach ($items as $it) {
                Database::query('UPDATE products SET stock = stock - ? WHERE id = ?', [$it['qty'], $it['product_id']]);
                Database::query(
                    "INSERT INTO stock_movements (product_id, movement_type, qty, reference_type, reference_id, notes, created_by)
                     VALUES (?, 'OUT', ?, 'SALES', ?, ?, ?)",
                    [$it['product_id'], $it['qty'], $id, "Pengiriman {$so['so_number']}", Auth::user()['id']]
                );
                $totalCogs += $it['qty'] * $it['purchase_price'];
            }
            Database::query("UPDATE sales_orders SET status='DELIVERED' WHERE id=?", [$id]);

            // Auto jurnal penjualan
            $ar  = Database::value("SELECT id FROM accounts WHERE code='1-1200'");
            $rev = Database::value("SELECT id FROM accounts WHERE code='4-1000'");
            $cogs = Database::value("SELECT id FROM accounts WHERE code='5-1000'");
            $inv = Database::value("SELECT id FROM accounts WHERE code='1-1300'");
            if ($ar && $rev && $cogs && $inv && $so['total'] > 0) {
                $jeNo = generateNumber('journal_entries', 'entry_number', 'JE');
                Database::query(
                    'INSERT INTO journal_entries (entry_number, entry_date, description, reference, created_by) VALUES (?,?,?,?,?)',
                    [$jeNo, date('Y-m-d'), "Penjualan {$so['so_number']}", $so['so_number'], Auth::user()['id']]
                );
                $jeId = (int)Database::lastId();
                // Piutang (D) / Pendapatan (K)
                Database::query('INSERT INTO journal_entry_lines (journal_id, account_id, debit, credit) VALUES (?,?,?,0)', [$jeId, $ar, $so['total']]);
                Database::query('INSERT INTO journal_entry_lines (journal_id, account_id, debit, credit) VALUES (?,?,0,?)', [$jeId, $rev, $so['total']]);
                // HPP (D) / Persediaan (K)
                if ($totalCogs > 0) {
                    Database::query('INSERT INTO journal_entry_lines (journal_id, account_id, debit, credit) VALUES (?,?,?,0)', [$jeId, $cogs, $totalCogs]);
                    Database::query('INSERT INTO journal_entry_lines (journal_id, account_id, debit, credit) VALUES (?,?,0,?)', [$jeId, $inv, $totalCogs]);
                }
            }
            Database::commit();
            setFlash('success', "SO {$so['so_number']} dikirim. Stok & jurnal terupdate.");
        } catch (Exception $ex) {
            Database::rollback();
            setFlash('danger', 'Gagal mengirim barang: ' . $ex->getMessage());
        }
        redirect('index.php?page=sales_view&id=' . $id);
    }
    redirect('index.php?page=sales');
}

function module_render(): void
{
    $statusFilter = $_GET['status'] ?? '';
    $sql = "SELECT s.*, c.name AS customer_name, u.full_name AS creator
            FROM sales_orders s
            JOIN customers c ON c.id = s.customer_id
            LEFT JOIN users u ON u.id = s.created_by";
    $params = [];
    if ($statusFilter !== '') {
        $sql .= " WHERE s.status = ?";
        $params[] = $statusFilter;
    }
    $sql .= " ORDER BY s.created_at DESC";
    $orders = Database::all($sql, $params);
    ?>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Daftar Sales Order</h3>
            <div class="card-tools">
                <a href="index.php?page=sales_form" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Buat SO Baru</a>
            </div>
        </div>
        <div class="card-body">
            <div class="mb-3">
                <a href="index.php?page=sales" class="btn btn-sm <?= $statusFilter === '' ? 'btn-dark' : 'btn-outline-dark' ?>">Semua</a>
                <?php foreach (['DRAFT','CONFIRMED','DELIVERED','CANCELLED'] as $st): ?>
                    <a href="index.php?page=sales&status=<?= $st ?>" class="btn btn-sm <?= $statusFilter === $st ? 'btn-dark' : 'btn-outline-dark' ?>"><?= $st ?></a>
                <?php endforeach; ?>
            </div>
            <table class="table table-bordered table-striped datatable">
                <thead>
                    <tr>
                        <th>No. SO</th><th>Tanggal</th><th>Customer</th>
                        <th class="text-right">Total</th><th>Status</th><th>Dibuat Oleh</th><th width="80">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($orders as $o): ?>
                    <tr>
                        <td><a href="index.php?page=sales_view&id=<?= $o['id'] ?>"><?= e($o['so_number']) ?></a></td>
                        <td><?= fdate($o['order_date']) ?></td>
                        <td><?= e($o['customer_name']) ?></td>
                        <td class="text-right"><?= money($o['total']) ?></td>
                        <td><?= statusBadge($o['status']) ?></td>
                        <td><?= e($o['creator'] ?? '-') ?></td>
                        <td><a href="index.php?page=sales_view&id=<?= $o['id'] ?>" class="btn btn-sm btn-info"><i class="fas fa-eye"></i></a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}
