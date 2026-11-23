<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
if (empty($_SESSION['admin_logged_in']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Administrator login required.']);
    exit;
}
require_once __DIR__ . '/db.php';

function sendJson(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

try {
    // Rank products by units sold using completed order-item records.
    $sql = "SELECT p.product_id, p.product_name, p.category, p.price,
                   p.product_quantity, p.image_path,
                   COALESCE(SUM(oi.quantity), 0) AS units_sold,
                   COALESCE(SUM(oi.quantity * oi.unit_price), 0) AS revenue,
                   COUNT(DISTINCT o.order_id) AS order_count
            FROM product p
            LEFT JOIN `order item` oi ON oi.product_id = p.product_id
            LEFT JOIN orders o ON o.order_id = oi.order_id
            GROUP BY p.product_id, p.product_name, p.category, p.price,
                     p.product_quantity, p.image_path
            HAVING units_sold > 0
            ORDER BY units_sold DESC, revenue DESC
            LIMIT 12";
    $result = $conn->query($sql);
    if (!$result) throw new RuntimeException('Could not load product sales.');
    $products = [];
    while ($row = $result->fetch_assoc()) {
        $row['product_id'] = (int)$row['product_id'];
        $row['price'] = (float)$row['price'];
        $row['product_quantity'] = (int)$row['product_quantity'];
        $row['units_sold'] = (int)$row['units_sold'];
        $row['revenue'] = (float)$row['revenue'];
        $row['order_count'] = (int)$row['order_count'];
        $row['repeat_customers'] = [];

        $customerSql = "SELECT c.full_name, COUNT(DISTINCT o.order_id) AS customer_orders
                        FROM `order item` oi
                        INNER JOIN orders o ON o.order_id = oi.order_id
                        INNER JOIN customer c ON c.customer_id = o.customer_id
                        WHERE oi.product_id = ?
                        GROUP BY c.customer_id, c.full_name
                        HAVING COUNT(DISTINCT o.order_id) >= 2
                        ORDER BY customer_orders DESC, c.full_name ASC
                        LIMIT 8";
        $customerStmt = $conn->prepare($customerSql);
        if (!$customerStmt) throw new RuntimeException('Could not prepare repeat-customer lookup.');
        $customerStmt->bind_param('i', $row['product_id']);
        $customerStmt->execute();
        $customerResult = $customerStmt->get_result();
        while ($customer = $customerResult->fetch_assoc()) {
            $row['repeat_customers'][] = [
                'name' => $customer['full_name'],
                'orders' => (int)$customer['customer_orders']
            ];
        }
        $customerStmt->close();
        $row['repeat_fans'] = count($row['repeat_customers']);
        $products[] = $row;
    }

    // Build useful admin alerts from existing stock and order-status data.
    $notifications = [];
    $lowStock = $conn->query("SELECT product_id, product_name, product_quantity
                              FROM product
                              WHERE is_active = 1 AND product_quantity <= 5
                              ORDER BY product_quantity ASC, product_name ASC
                              LIMIT 10");
    if (!$lowStock) throw new RuntimeException('Could not load stock alerts.');
    while ($item = $lowStock->fetch_assoc()) {
        $qty = (int)$item['product_quantity'];
        $notifications[] = [
            'type' => 'stock',
            'product_id' => (int)$item['product_id'],
            'icon' => '⚠️',
            'title' => $qty === 0 ? 'Product out of stock' : 'Low stock alert',
            'details' => $item['product_name'] . ' — ' . $qty . ' remaining',
            'time' => 'Current stock'
        ];
    }

    $pending = $conn->query("SELECT ol.order_id, ol.receipt_no, ol.status_name
                             FROM order_log ol
                             WHERE LOWER(ol.status_name) LIKE '%pending%'
                             ORDER BY ol.order_id DESC
                             LIMIT 10");
    if (!$pending) throw new RuntimeException('Could not load pending-order alerts.');
    while ($item = $pending->fetch_assoc()) {
        $notifications[] = [
            'type' => 'order',
            'order_id' => (int)$item['order_id'],
            'receipt_no' => (string)$item['receipt_no'],
            'icon' => '🧾',
            'title' => 'Order awaiting review',
            'details' => 'Receipt ' . $item['receipt_no'] . ' — ' . $item['status_name'],
            'time' => 'Order #' . $item['order_id']
        ];
    }

    sendJson(['success' => true, 'products' => $products, 'notifications' => $notifications]);
} catch (Throwable $e) {
    error_log('Admin insights error: ' . $e->getMessage());
    sendJson(['success' => false, 'message' => 'Dashboard insights could not be loaded. Check the PHP error log.'], 500);
}
?>
