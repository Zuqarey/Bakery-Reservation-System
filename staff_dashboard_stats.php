<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
if (empty($_SESSION['staff_logged_in']) || empty($_SESSION['staff_id']) || ($_SESSION['role'] ?? '') !== 'staff') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please log in as staff.']);
    exit;
}
require_once __DIR__ . '/db.php';
$activeStaffCheck = $conn->prepare("SELECT status FROM staff WHERE staff_id = ? LIMIT 1");
$activeStaffId = (int)$_SESSION['staff_id'];
$activeStaffCheck->bind_param('i', $activeStaffId);
$activeStaffCheck->execute();
$activeStaffRow = $activeStaffCheck->get_result()->fetch_assoc();
if (!$activeStaffRow || strcasecmp(trim((string)$activeStaffRow['status']), 'Active') !== 0) {
    $_SESSION = [];
    session_destroy();
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'This staff account is no longer active. Please contact an administrator.']);
    exit;
}
date_default_timezone_set('Asia/Kuala_Lumpur');
try {
    $active = (int)$conn->query("SELECT COUNT(*) AS total FROM orders WHERE UPPER(TRIM(status)) NOT IN ('COMPLETED','CANCELLED')")->fetch_assoc()['total'];
    $completed = (int)$conn->query("SELECT COUNT(*) AS total FROM orders WHERE UPPER(TRIM(status)) = 'COMPLETED' AND DATE(`date order`) = CURDATE()")->fetch_assoc()['total'];
    $lowStock = (int)$conn->query("SELECT COUNT(*) AS total FROM ingredients WHERE LOWER(TRIM(status)) = 'low'")->fetch_assoc()['total'];
    $upcoming = (int)$conn->query("SELECT COUNT(*) AS total FROM orders WHERE LOWER(order_type) LIKE '%pickup%' AND pickup_datetime >= NOW() AND UPPER(TRIM(status)) NOT IN ('COMPLETED','CANCELLED')")->fetch_assoc()['total'];

    $sql = "SELECT o.order_id, c.full_name AS customer_name, o.pickup_datetime, o.status
            FROM orders o LEFT JOIN customer c ON c.customer_id = o.customer_id
            WHERE LOWER(o.order_type) LIKE '%pickup%' AND o.pickup_datetime >= NOW()
              AND UPPER(TRIM(o.status)) NOT IN ('COMPLETED','CANCELLED')
            ORDER BY o.pickup_datetime ASC LIMIT 6";
    $result = $conn->query($sql);
    $pickups = [];
    while ($row = $result->fetch_assoc()) {
        $row['order_label'] = 'Order #' . (int)$row['order_id'];
        $row['pickup_display'] = $row['pickup_datetime'] ? date('d M Y, h:i A', strtotime($row['pickup_datetime'])) : 'Not scheduled';
        $row['status'] = ucfirst(strtolower((string)$row['status']));
        $pickups[] = $row;
    }
    echo json_encode(['success' => true, 'stats' => [
        'active_orders' => $active,
        'completed_today' => $completed,
        'low_stock' => $lowStock,
        'upcoming_pickups' => $upcoming
    ], 'upcoming_pickups' => $pickups]);
} catch (Throwable $e) {
    error_log('Staff dashboard overview error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not load dashboard overview. Check the PHP error log.']);
}
?>
