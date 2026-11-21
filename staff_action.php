<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
if (empty($_SESSION['staff_logged_in']) || empty($_SESSION['staff_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please log in as staff.']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST is required.']);
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
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $input['action'] ?? '';

try {
    if ($action === 'update_stock') {
        $productId = filter_var($input['product_id'] ?? null, FILTER_VALIDATE_INT);
        $delta = filter_var($input['delta'] ?? null, FILTER_VALIDATE_INT);
        if (!$productId || !in_array($delta, [-1, 1], true)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid stock update.']);
            exit;
        }
        if ($delta === 1) {
            $stmt = $conn->prepare('UPDATE product SET product_quantity = product_quantity + 1 WHERE product_id = ?');
        } else {
            $stmt = $conn->prepare('UPDATE product SET product_quantity = product_quantity - 1 WHERE product_id = ? AND product_quantity > 0');
        }
        $stmt->bind_param('i', $productId);
        $stmt->execute();
        if ($stmt->affected_rows !== 1) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'Stock was not changed. The product may not exist or stock is already zero.']);
            exit;
        }
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'update_order_status') {
        $orderId = filter_var($input['order_id'] ?? null, FILTER_VALIDATE_INT);
        $status = strtoupper(trim((string)($input['status'] ?? '')));
        $allowed = ['NEW', 'PREPARING', 'READY', 'COMPLETED'];
        if (!$orderId || !in_array($status, $allowed, true)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid order status update.']);
            exit;
        }
        $check = $conn->prepare('SELECT order_id FROM orders WHERE order_id = ? LIMIT 1');
        $check->bind_param('i', $orderId);
        $check->execute();
        if ($check->get_result()->num_rows !== 1) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Order not found.']);
            exit;
        }
        $update = $conn->prepare('UPDATE orders SET status = ? WHERE order_id = ?');
        $update->bind_param('si', $status, $orderId);
        $update->execute();
        if ($update->affected_rows < 0) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Order status could not be saved.']);
            exit;
        }
        echo json_encode(['success' => true]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
} catch (Throwable $e) {
    error_log('Staff action API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'The requested change could not be saved. Check the PHP error log.']);
}
?>
