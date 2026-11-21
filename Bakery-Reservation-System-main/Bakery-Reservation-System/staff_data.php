<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
if (empty($_SESSION['staff_logged_in']) || empty($_SESSION['staff_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please log in as staff.']);
    exit;
}
require_once __DIR__ . '/db.php';

try {
    $inventory = [];
    $result = $conn->query("SELECT product_id, product_name, price, product_quantity, category, image_path FROM product WHERE is_active = 1 ORDER BY product_name ASC");
    while ($row = $result->fetch_assoc()) $inventory[] = $row;

    $sql = "SELECT
                o.order_id,
                c.full_name AS customer_name,
                o.order_type,
                o.outlet,
                o.pickup_datetime,
                o.special_instructions,
                o.`date order` AS order_created,
                COALESCE((SELECT p.total FROM payment p WHERE p.order_id = o.order_id ORDER BY p.reference_id DESC LIMIT 1), 0) AS total,
                (SELECT pm.methode_name
                   FROM payment p
                   LEFT JOIN paymen_methode pm ON pm.reference_id = p.reference_id
                  WHERE p.order_id = o.order_id
                  ORDER BY p.reference_id DESC LIMIT 1) AS payment_method,
                o.status AS status
            FROM orders o
            LEFT JOIN customer c ON c.customer_id = o.customer_id
            ORDER BY o.`date order` DESC, o.order_id DESC";
    $ordersResult = $conn->query($sql);
    $orders = [];
    $orderIds = [];
    while ($row = $ordersResult->fetch_assoc()) {
        $rawStatus = strtoupper(trim((string)($row['status'] ?? 'NEW')));
        $allowedStatuses = ['NEW', 'PREPARING', 'READY', 'COMPLETED'];
        $row['status'] = in_array($rawStatus, $allowedStatuses, true) ? $rawStatus : 'NEW';
        $created = $row['order_created'] ?: $row['pickup_datetime'];
        $row['order_time'] = $created ? date('d M Y, h:i A', strtotime($created)) : 'Time not recorded';
        $row['items'] = [];
        $row['summary'] = '';
        $orders[] = $row;
        $orderIds[] = (int)$row['order_id'];
    }

    if (count($orderIds) > 0) {
        $ids = implode(',', array_map('intval', $orderIds));
        $itemsSql = "SELECT oi.order_id, oi.quantity, oi.unit_price, p.product_name
                     FROM `order item` oi
                     LEFT JOIN product p ON p.product_id = oi.product_id
                     WHERE oi.order_id IN ($ids)
                     ORDER BY oi.order_id DESC, p.product_name ASC";
        $itemsResult = $conn->query($itemsSql);
        $itemsByOrder = [];
        while ($item = $itemsResult->fetch_assoc()) {
            $item['line_total'] = (float)$item['quantity'] * (float)$item['unit_price'];
            $itemsByOrder[(int)$item['order_id']][] = $item;
        }
        foreach ($orders as &$order) {
            $order['items'] = $itemsByOrder[(int)$order['order_id']] ?? [];
            $names = [];
            foreach ($order['items'] as $item) $names[] = $item['quantity'] . ' × ' . $item['product_name'];
            $order['summary'] = implode(', ', $names);
        }
        unset($order);
    }

    echo json_encode(['success' => true, 'inventory' => $inventory, 'orders' => $orders]);
} catch (Throwable $e) {
    error_log('Staff data API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not retrieve staff data. Check the PHP error log.']);
}
?>
