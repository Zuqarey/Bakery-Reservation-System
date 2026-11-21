<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (empty($_SESSION['customer_logged_in']) || empty($_SESSION['customer_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please log in as a customer to view your orders.']);
    exit;
}

require_once __DIR__ . '/db.php';
$customerId = (int) $_SESSION['customer_id'];

try {
    $sql = "SELECT
                o.order_id,
                o.status,
                o.order_type,
                o.outlet,
                o.pickup_datetime,
                o.`date order` AS order_date,
                COALESCE((
                    SELECT p.total
                    FROM payment p
                    WHERE p.order_id = o.order_id
                    ORDER BY p.reference_id DESC
                    LIMIT 1
                ), 0) AS total,
                COALESCE((
                    SELECT GROUP_CONCAT(
                        CONCAT(oi.quantity, ' × ', COALESCE(pr.product_name, 'Product'))
                        ORDER BY pr.product_name SEPARATOR ', '
                    )
                    FROM `order item` oi
                    LEFT JOIN product pr ON pr.product_id = oi.product_id
                    WHERE oi.order_id = o.order_id
                ), 'No item details') AS items
            FROM orders o
            WHERE o.customer_id = ?
            ORDER BY o.`date order` DESC, o.order_id DESC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Could not prepare the customer orders query.');
    }
    $stmt->bind_param('i', $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $orders = [];

    while ($row = $result->fetch_assoc()) {
        $row['order_id'] = (int) $row['order_id'];
        $row['total'] = (float) $row['total'];
        $row['status'] = trim((string) ($row['status'] ?? 'Pending')) ?: 'Pending';
        $orders[] = $row;
    }

    echo json_encode(['success' => true, 'orders' => $orders]);
} catch (Throwable $e) {
    error_log('Customer orders API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not retrieve your orders. Check the PHP error log.']);
}
?>
