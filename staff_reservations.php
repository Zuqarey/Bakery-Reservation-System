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

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $reservationId = filter_var($input['reservation_id'] ?? null, FILTER_VALIDATE_INT);
        $status = trim((string)($input['status'] ?? ''));
        $allowedStatuses = ['Pending', 'Confirmed', 'Cancelled', 'Completed'];
        if (!$reservationId || !in_array($status, $allowedStatuses, true)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid reservation or status.']);
            exit;
        }
        $update = $conn->prepare('UPDATE reservation SET status = ? WHERE reservation_id = ?');
        $update->bind_param('si', $status, $reservationId);
        $update->execute();
        if ($update->affected_rows === 0) {
            $check = $conn->prepare('SELECT reservation_id, status FROM reservation WHERE reservation_id = ? LIMIT 1');
            $check->bind_param('i', $reservationId);
            $check->execute();
            $existing = $check->get_result()->fetch_assoc();
            if (!$existing) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Reservation not found.']);
                exit;
            }
            if ($existing['status'] !== $status) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Reservation status could not be saved.']);
                exit;
            }
        }
        echo json_encode(['success' => true, 'reservation_id' => $reservationId, 'status' => $status]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'GET or POST is required.']);
        exit;
    }

    $sql = "SELECT r.reservation_id, r.phone, r.outlet, r.reservation_date,
                   r.reservation_time, r.guests, r.table_name, r.special_request,
                   r.status, c.full_name AS customer_name
            FROM reservation r
            LEFT JOIN customer c ON c.customer_id = r.customer_id
            ORDER BY r.reservation_date ASC, r.reservation_time ASC, r.reservation_id DESC";
    $result = $conn->query($sql);
    $reservations = [];
    while ($row = $result->fetch_assoc()) {
        $id = (int)$row['reservation_id'];
        $reservations[] = [
            'reservation_id' => $id,
            'reservation_code' => 'RES' . str_pad((string)$id, 5, '0', STR_PAD_LEFT),
            'customer_name' => $row['customer_name'] ?? 'Customer',
            'phone' => $row['phone'] ?? '',
            'outlet' => $row['outlet'] ?? '',
            'date' => $row['reservation_date'] ?? '',
            'time' => substr((string)($row['reservation_time'] ?? ''), 0, 5),
            'guests' => (int)($row['guests'] ?? 0),
            'table_name' => $row['table_name'] ?? '',
            'special_request' => $row['special_request'] ?? '',
            'status' => $row['status'] ?? 'Pending'
        ];
    }
    echo json_encode(['success' => true, 'reservations' => $reservations], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('Staff reservations API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to process reservations. Check the PHP error log.']);
}
?>
