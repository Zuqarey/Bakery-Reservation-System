<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db.php';
date_default_timezone_set('Asia/Kuala_Lumpur');

function respond(int $status, array $body): void {
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['success' => false, 'message' => 'Use POST to submit a reservation.']);
}

if (empty($_SESSION['customer_logged_in']) || empty($_SESSION['customer_id'])) {
    respond(401, ['success' => false, 'message' => 'Please log in before making a reservation.']);
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    respond(400, ['success' => false, 'message' => 'Invalid reservation data.']);
}

$customerId = (int)$_SESSION['customer_id'];
$phone = trim((string)($data['phone'] ?? ''));
$outlet = trim((string)($data['outlet'] ?? ''));
$date = trim((string)($data['date'] ?? ''));
$time = trim((string)($data['time'] ?? ''));
$guests = filter_var($data['guests'] ?? null, FILTER_VALIDATE_INT);
$tableName = trim((string)($data['table'] ?? ''));
$notes = trim((string)($data['notes'] ?? ''));
$allowedOutlets = ['Kangar Jaya Mall Perlis', 'Aman Central Kedah', 'Queensbay Mall Penang'];

if ($phone === '' || !preg_match('/^[0-9+\\-\\s]{9,20}$/', $phone)) {
    respond(422, ['success' => false, 'message' => 'Enter a valid phone number.']);
}
if (!in_array($outlet, $allowedOutlets, true)) {
    respond(422, ['success' => false, 'message' => 'Please select a valid outlet.']);
}
if (!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $date) || !preg_match('/^\\d{2}:\\d{2}$/', $time)) {
    respond(422, ['success' => false, 'message' => 'Please select a valid reservation date and time.']);
}
$parsedDate = DateTime::createFromFormat('!Y-m-d', $date);
$parsedTime = DateTime::createFromFormat('!H:i', $time);
if (!$parsedDate || $parsedDate->format('Y-m-d') !== $date || !$parsedTime || $parsedTime->format('H:i') !== $time) {
    respond(422, ['success' => false, 'message' => 'The reservation date or time is invalid.']);
}
if ($parsedDate->format('Y-m-d') . ' ' . $parsedTime->format('H:i') <= date('Y-m-d H:i')) {
    respond(422, ['success' => false, 'message' => 'Please choose a future date and time.']);
}
if ($guests === false || $guests < 1 || $guests > 6) {
    respond(422, ['success' => false, 'message' => 'The number of guests must be between 1 and 6.']);
}
if (!preg_match('/^T(?:[1-9]|1[0-5])$/', $tableName)) {
    respond(422, ['success' => false, 'message' => 'Please select a valid table.']);
}
$tableNumber = (int)substr($tableName, 1);
$tableCapacity = (int)ceil($tableNumber / 5) * 2;
if ($guests > $tableCapacity) {
    respond(422, ['success' => false, 'message' => 'The selected table does not have enough seats.']);
}

try {
    // Verify that the customer still exists and belongs to the authenticated session.
    $customerCheck = $conn->prepare('SELECT customer_id FROM customer WHERE customer_id = ? LIMIT 1');
    $customerCheck->bind_param('i', $customerId);
    $customerCheck->execute();
    if ($customerCheck->get_result()->num_rows !== 1) {
        respond(401, ['success' => false, 'message' => 'Your customer account could not be verified. Please log in again.']);
    }

    $stmt = $conn->prepare('INSERT INTO reservation
        (customer_id, phone, outlet, reservation_date, reservation_time, guests, table_name, special_request, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, \'Pending\')');
    $stmt->bind_param('issssiss', $customerId, $phone, $outlet, $date, $time, $guests, $tableName, $notes);
    $stmt->execute();

    $reservationId = (int)$conn->insert_id;
    respond(201, [
        'success' => true,
        'reservation' => [
            'id' => 'RES' . str_pad((string)$reservationId, 5, '0', STR_PAD_LEFT),
            'reservation_id' => $reservationId,
            'status' => 'Pending'
        ],
        'message' => 'Your reservation has been saved successfully.'
    ]);
} catch (mysqli_sql_exception $e) {
    if ((int)$e->getCode() === 1062) {
        respond(409, ['success' => false, 'message' => 'That table is already reserved for this outlet, date and time. Please choose another table or time.']);
    }
    error_log('Reservation insert failed: ' . $e->getMessage());
    respond(500, ['success' => false, 'message' => 'The reservation could not be saved. Check the PHP error log.']);
} catch (Throwable $e) {
    error_log('Reservation error: ' . $e->getMessage());
    respond(500, ['success' => false, 'message' => 'An unexpected error prevented the reservation from being saved.']);
}
?>
