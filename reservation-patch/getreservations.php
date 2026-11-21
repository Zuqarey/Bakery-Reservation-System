<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db.php';

if (empty($_SESSION['customer_logged_in']) || empty($_SESSION['customer_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please log in to view your reservations.']);
    exit;
}

$customerId = (int)$_SESSION['customer_id'];
try {
    $stmt = $conn->prepare('SELECT reservation_id, outlet, reservation_date, reservation_time,
                                   table_name, guests, status
                            FROM reservation
                            WHERE customer_id = ?
                            ORDER BY reservation_date ASC, reservation_time ASC, reservation_id DESC');
    $stmt->bind_param('i', $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $reservations = [];
    while ($row = $result->fetch_assoc()) {
        $id = (int)$row['reservation_id'];
        $reservations[] = [
            'id' => 'RES' . str_pad((string)$id, 5, '0', STR_PAD_LEFT),
            'reservation_id' => $id,
            'outlet' => $row['outlet'],
            'date' => $row['reservation_date'],
            'time' => substr($row['reservation_time'], 0, 5),
            'table' => $row['table_name'],
            'guests' => (int)$row['guests'],
            'status' => $row['status']
        ];
    }
    echo json_encode(['success' => true, 'reservations' => $reservations], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('Get reservations failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to retrieve reservations.']);
}
?>
