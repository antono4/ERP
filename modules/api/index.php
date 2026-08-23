<?php
// -----------------------------------------------------
// Modul API: REST Endpoints (JSON)
// -----------------------------------------------------

function module_handle(): void
{
    // tidak ada POST handler — API diakses via GET
}

function module_render(): void
{
    // redirect ke halaman dokumentasi
    header('Location: index.php?page=dashboard');
    exit;
}

// API handler dipanggil langsung dari index.php sebelum layout
function apiHandler(): void
{
    header('Content-Type: application/json');

    $action = $_GET['action'] ?? '';
    $apiKey = $_GET['api_key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '';

    // Simple API key check
    $validKey = Database::value('SELECT password FROM users WHERE username = "api" LIMIT 1');
    if (!$validKey || !password_verify($apiKey, $validKey)) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid API key']);
        exit;
    }

    switch ($action) {
        case 'products':
            $products = Database::all('SELECT id, code, name, selling_price, stock FROM products WHERE status = 1');
            echo json_encode(['success' => true, 'data' => $products]);
            break;

        case 'product':
            $id = (int)($_GET['id'] ?? 0);
            $product = Database::row('SELECT * FROM products WHERE id = ?', [$id]);
            if ($product) {
                echo json_encode(['success' => true, 'data' => $product]);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Product not found']);
            }
            break;

        case 'sales_orders':
            $status = $_GET['status'] ?? '';
            $sql = 'SELECT s.*, c.name AS customer_name FROM sales_orders s JOIN customers c ON c.id = s.customer_id';
            $params = [];
            if ($status) { $sql .= ' WHERE s.status = ?'; $params[] = $status; }
            $sql .= ' ORDER BY s.created_at DESC LIMIT 100';
            $orders = Database::all($sql, $params);
            echo json_encode(['success' => true, 'data' => $orders]);
            break;

        case 'stock':
            $products = Database::all('SELECT id, code, name, stock, min_stock FROM products WHERE status = 1 ORDER BY code');
            echo json_encode(['success' => true, 'data' => $products]);
            break;

        case 'stock_movements':
            $productId = (int)($_GET['product_id'] ?? 0);
            $movements = Database::all(
                'SELECT m.*, p.code, p.name FROM stock_movements m JOIN products p ON p.id = m.product_id WHERE m.product_id = ? ORDER BY m.created_at DESC LIMIT 50',
                [$productId]
            );
            echo json_encode(['success' => true, 'data' => $movements]);
            break;

        case 'create_sales_order':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                exit;
            }
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input || !isset($input['customer_id'], $input['items'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing customer_id or items']);
                exit;
            }
            $items = $input['items'];
            $total = 0;
            foreach ($items as $it) {
                $total += ($it['qty'] ?? 0) * ($it['price'] ?? 0);
            }
            $soNo = generateNumber('sales_orders', 'so_number', 'SO');
            Database::query(
                'INSERT INTO sales_orders (so_number, customer_id, order_date, status, total, notes, created_by) VALUES (?,?,?,?,?,?,?)',
                [$soNo, (int)$input['customer_id'], $input['order_date'] ?? date('Y-m-d'), 'DRAFT', $total, $input['notes'] ?? '', 1]
            );
            $soId = (int)Database::lastId();
            foreach ($items as $it) {
                Database::query(
                    'INSERT INTO sales_order_items (so_id, product_id, qty, price, subtotal) VALUES (?,?,?,?,?)',
                    [$soId, (int)$it['product_id'], (float)$it['qty'], (float)$it['price'], (float)$it['qty'] * (float)$it['price']]
                );
            }
            echo json_encode(['success' => true, 'so_number' => $soNo, 'so_id' => $soId]);
            break;

        case 'create_purchase_order':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                exit;
            }
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input || !isset($input['supplier_id'], $input['items'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing supplier_id or items']);
                exit;
            }
            $items = $input['items'];
            $total = 0;
            foreach ($items as $it) {
                $total += ($it['qty'] ?? 0) * ($it['price'] ?? 0);
            }
            $poNo = generateNumber('purchase_orders', 'po_number', 'PO');
            Database::query(
                'INSERT INTO purchase_orders (po_number, supplier_id, order_date, status, total, notes, created_by) VALUES (?,?,?,?,?,?,?)',
                [$poNo, (int)$input['supplier_id'], $input['order_date'] ?? date('Y-m-d'), 'DRAFT', $total, $input['notes'] ?? '', 1]
            );
            $poId = (int)Database::lastId();
            foreach ($items as $it) {
                Database::query(
                    'INSERT INTO purchase_order_items (po_id, product_id, qty, price, subtotal) VALUES (?,?,?,?,?)',
                    [$poId, (int)$it['product_id'], (float)$it['qty'], (float)$it['price'], (float)$it['qty'] * (float)$it['price']]
                );
            }
            echo json_encode(['success' => true, 'po_number' => $poNo, 'po_id' => $poId]);
            break;

        default:
            http_response_code(404);
            echo json_encode(['error' => 'Unknown action', 'available' => ['products', 'product', 'sales_orders', 'stock', 'stock_movements', 'create_sales_order', 'create_purchase_order']]);
    }
    exit;
}
