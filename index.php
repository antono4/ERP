<?php
// -----------------------------------------------------
// MiniERP - Front Controller
// -----------------------------------------------------

require_once __DIR__ . '/config/config.php';
require_once BASE_PATH . '/core/Database.php';
require_once BASE_PATH . '/core/functions.php';
require_once BASE_PATH . '/core/Auth.php';

$page = $_GET['page'] ?? (Auth::check() ? 'dashboard' : 'login');

$routes = [
    'login'      => 'modules/auth/login.php',
    'logout'     => 'modules/auth/logout.php',
    'dashboard'  => 'modules/dashboard/index.php',

    // Master Data
    'categories' => 'modules/master/categories.php',
    'products'   => 'modules/master/products.php',
    'customers'  => 'modules/master/customers.php',
    'suppliers'  => 'modules/master/suppliers.php',
    'users'      => 'modules/master/users.php',

    // Transactions
    'purchase'       => 'modules/purchasing/orders.php',
    'purchase_form'  => 'modules/purchasing/form.php',
    'purchase_view'  => 'modules/purchasing/view.php',
    'sales'      => 'modules/sales/orders.php',
    'sales_form' => 'modules/sales/form.php',
    'sales_view' => 'modules/sales/view.php',

    // Inventory
    'stock'      => 'modules/inventory/stock.php',
    'movements'  => 'modules/inventory/movements.php',

    // Finance
    'accounts'   => 'modules/finance/accounts.php',
    'journal'    => 'modules/finance/journal.php',

    // Reports
    'report_sales'    => 'modules/reports/sales.php',
    'report_purchase' => 'modules/reports/purchase.php',
    'report_stock'    => 'modules/reports/stock.php',
    'report_profit'   => 'modules/reports/profit.php',

    // v2: Invoice & Payment
    'sales_invoice'      => 'modules/billing/sales_invoice.php',
    'purchase_invoice'   => 'modules/billing/purchase_invoice.php',
    'payments'           => 'modules/billing/payments.php',

    // v2: Delivery Order
    'delivery'       => 'modules/delivery/orders.php',
    'delivery_form'  => 'modules/delivery/form.php',

    // v2: Returns
    'sales_return'       => 'modules/returns/sales.php',
    'purchase_return'    => 'modules/returns/purchase.php',

    // v2: Stock Opname
    'opname'       => 'modules/inventory/opname.php',
    'opname_form'  => 'modules/inventory/opname_form.php',
    'opname_view'  => 'modules/inventory/opname_view.php',

    // v2: Price Levels
    'price_levels' => 'modules/master/price_levels.php',

    // v2: Activity Log
    'activity_log' => 'modules/system/activity_log.php',
];

$publicPages = ['login'];
$noLayout = ['login', 'logout', 'api'];

// AJAX endpoint: harga khusus per customer+produk
if ($page === 'api') {
    Auth::requireLogin();
    header('Content-Type: application/json');
    $action = $_GET['action'] ?? '';
    if ($action === 'price_level') {
        $productId = (int)($_GET['product_id'] ?? 0);
        $customerId = (int)($_GET['customer_id'] ?? 0);
        $level = Database::row(
            'SELECT price FROM price_levels WHERE product_id = ? AND customer_id = ?',
            [$productId, $customerId]
        );
        $base = Database::row('SELECT selling_price, stock FROM products WHERE id = ?', [$productId]);
        echo json_encode([
            'price' => $level ? (float)$level['price'] : (float)$base['selling_price'],
            'base_price' => (float)$base['selling_price'],
            'stock' => (float)$base['stock'],
            'has_level' => $level !== null,
        ]);
        exit;
    }
    http_response_code(404);
    echo json_encode(['error' => 'Unknown action']);
    exit;
}

if (!in_array($page, $publicPages, true)) {
    Auth::requireLogin();
}

if (!isset($routes[$page])) {
    http_response_code(404);
    echo '<h1>404 - Halaman tidak ditemukan</h1><a href="index.php">Kembali</a>';
    exit;
}

// Load modul: modul mendefinisikan module_handle() dan module_render()
require BASE_PATH . '/' . $routes[$page];

// Proses aksi (POST) sebelum ada output
if (function_exists('module_handle')) {
    module_handle();
}

if (in_array($page, $noLayout, true)) {
    module_render();
    exit;
}

include BASE_PATH . '/layouts/header.php';
include BASE_PATH . '/layouts/sidebar.php';
?>
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <h1 class="m-0"><?= e($pageTitle ?? APP_NAME) ?></h1>
        </div>
    </div>
    <div class="content">
        <div class="container-fluid">
            <?php if ($flash = getFlash()): ?>
                <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                    <?= e($flash['message']) ?>
                </div>
            <?php endif; ?>
            <?php module_render(); ?>
        </div>
    </div>
</div>
<?php include BASE_PATH . '/layouts/footer.php';
