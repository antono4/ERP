<?php
// -----------------------------------------------------
// Helper Functions
// -----------------------------------------------------

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function e(?string $str): string
{
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

function money($value): string
{
    return CURRENCY . ' ' . number_format((float)$value, 0, ',', '.');
}

function fdate(?string $date): string
{
    if (!$date) return '-';
    return date(DATE_FORMAT, strtotime($date));
}

function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

function statusBadge(string $status): string
{
    $map = [
        'DRAFT' => 'secondary',
        'APPROVED' => 'warning',
        'RECEIVED' => 'success',
        'CONFIRMED' => 'info',
        'DELIVERED' => 'success',
        'CANCELLED' => 'danger',
        'IN' => 'success',
        'OUT' => 'danger',
        'ASSET' => 'primary',
        'LIABILITY' => 'warning',
        'EQUITY' => 'info',
        'REVENUE' => 'success',
        'EXPENSE' => 'danger',
        'admin' => 'danger',
        'manager' => 'warning',
        'staff' => 'info',
    ];
    $color = $map[$status] ?? 'secondary';
    return '<span class="badge badge-' . $color . '">' . e($status) . '</span>';
}

function generateNumber(string $table, string $column, string $prefix): string
{
    $year = date('Y');
    $row = Database::row(
        "SELECT {$column} FROM {$table} WHERE {$column} LIKE ? ORDER BY {$column} DESC LIMIT 1",
        [$prefix . '-' . $year . '-%']
    );
    $next = 1;
    if ($row) {
        $parts = explode('-', $row[$column]);
        $next = (int)end($parts) + 1;
    }
    return sprintf('%s-%s-%04d', $prefix, $year, $next);
}

function activeMenu(string $page, string $current): string
{
    return $page === $current ? 'active' : '';
}

function menuOpen(array $pages, string $current): string
{
    return in_array($current, $pages, true) ? 'menu-open' : '';
}

function logActivity(string $module, string $action, string $description = ''): void
{
    try {
        Database::query(
            'INSERT INTO activity_logs (user_id, action, module, description, ip_address) VALUES (?,?,?,?,?)',
            [Auth::user()['id'] ?? null, $action, $module, $description, $_SERVER['REMOTE_ADDR'] ?? '-']
        );
    } catch (Exception $e) {
        // jangan ganggu proses utama jika log gagal
    }
}

function autoInvoiceStatus(string $table, int $invoiceId): void
{
    $inv = Database::row("SELECT total, paid, due_date FROM {$table} WHERE id = ?", [$invoiceId]);
    if (!$inv) return;
    $status = 'UNPAID';
    if ($inv['paid'] >= $inv['total'] && $inv['total'] > 0) {
        $status = 'PAID';
    } elseif ($inv['paid'] > 0) {
        $status = 'PARTIAL';
    } elseif ($inv['due_date'] < date('Y-m-d')) {
        $status = 'OVERDUE';
    }
    Database::query("UPDATE {$table} SET status = ? WHERE id = ?", [$status, $invoiceId]);
}
