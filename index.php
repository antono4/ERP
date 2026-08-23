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

    // v3: HR & Payroll
    'departments' => 'modules/hr/departments.php',
    'positions'   => 'modules/hr/positions.php',
    'employees'   => 'modules/hr/employees.php',
    'attendance'  => 'modules/hr/attendance.php',
    'payroll'     => 'modules/hr/payroll.php',
    'payroll_view' => 'modules/hr/payroll_view.php',

    // v3: Manufacturing
    'bom'         => 'modules/manufacturing/bom.php',
    'work_orders' => 'modules/manufacturing/work_orders.php',
    'wo_view'     => 'modules/manufacturing/wo_view.php',

    // v3: WMS
    'warehouses'  => 'modules/wms/warehouses.php',
    'transfers'   => 'modules/wms/transfers.php',
    'transfer_form' => 'modules/wms/transfer_form.php',
    'transfer_view' => 'modules/wms/transfer_view.php',

    // v3: Accounting lanjutan
    'general_ledger' => 'modules/finance/general_ledger.php',
    'balance_sheet'  => 'modules/reports/balance_sheet.php',

    // v3: Project
    'projects'    => 'modules/projects/projects.php',
    'project_view' => 'modules/projects/view.php',
    'timesheets'  => 'modules/projects/timesheets.php',

    // v3: Assets
    'assets'      => 'modules/assets/assets.php',
    'depreciation' => 'modules/assets/depreciation.php',

    // v3: Quality Control
    'qc'          => 'modules/qc/inspections.php',
    'qc_form'     => 'modules/qc/form.php',

    // v3: Budget
    'budgets'     => 'modules/budget/budgets.php',
    'budget_view' => 'modules/budget/budget_view.php',

    // v3: Settings & POS
    'settings'    => 'modules/system/settings.php',
    'pos'         => 'modules/pos/index.php',

    // v4: CRM
    'leads'       => 'modules/crm/leads.php',
    'opportunities' => 'modules/crm/opportunities.php',

    // v4: Tax
    'taxes'       => 'modules/tax/taxes.php',
    'efaktur'     => 'modules/tax/efaktur.php',

    // v4: Branch
    'branches'    => 'modules/branches/branches.php',

    // v4: Shipment
    'shipments'   => 'modules/shipment/shipments.php',

    // v4: Landed Cost
    'cost'        => 'modules/cost/landed.php',

    // v4: Commission
    'commissions' => 'modules/commission/commissions.php',

    // v4: Service & Helpdesk
    'tickets'     => 'modules/service/tickets.php',
    'kb'          => 'modules/service/kb.php',

    // v4: Documents
    'documents'   => 'modules/documents/documents.php',

    // v4: Currency
    'currencies'  => 'modules/currency/currencies.php',

    // v4: Approval
    'approval_rules' => 'modules/approval/rules.php',

    // v4: Forecast & MRP
    'forecast'    => 'modules/forecast/mrp.php',
];

$publicPages = ['login'];
$noLayout = ['login', 'logout', 'api'];

// API endpoints
if ($page === 'api') {
    $action = $_GET['action'] ?? '';
    if ($action === 'price_level') {
        Auth::requireLogin();
        header('Content-Type: application/json');
        $productId = (int)($_GET['product_id'] ?? 0);
        $customerId = (int)($_GET['customer_id'] ?? 0);
        $level = Database::row('SELECT price FROM price_levels WHERE product_id = ? AND customer_id = ?', [$productId, $customerId]);
        $base = Database::row('SELECT selling_price, stock FROM products WHERE id = ?', [$productId]);
        echo json_encode([
            'price' => $level ? (float)$level['price'] : (float)$base['selling_price'],
            'base_price' => (float)$base['selling_price'],
            'stock' => (float)$base['stock'],
            'has_level' => $level !== null,
        ]);
        exit;
    }
    // REST API
    if (in_array($action, ['products', 'product', 'sales_orders', 'stock', 'stock_movements', 'create_sales_order', 'create_purchase_order'], true)) {
        require_once BASE_PATH . '/modules/api/index.php';
        apiHandler();
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
