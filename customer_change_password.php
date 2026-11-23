<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

function reply(int $code, array $data): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_SESSION['customer_logged_in']) || ($_SESSION['role'] ?? 'customer') !== 'customer' || empty($_SESSION['customer_id'])) {
    reply(401, ['success' => false, 'message' => 'Please log in as a customer.']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    reply(405, ['success' => false, 'message' => 'POST required.']);
}

require_once __DIR__ . '/db.php';
if (!isset($conn) || $conn->connect_error) {
    reply(500, ['success' => false, 'message' => 'Database connection unavailable.']);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    reply(400, ['success' => false, 'message' => 'Invalid request body.']);
}

$current = (string)($input['current_password'] ?? '');
$newPass = (string)($input['new_password'] ?? '');
$confirm = (string)($input['confirm_password'] ?? '');

if ($current === '') {
    reply(400, ['success' => false, 'message' => 'Please enter your current password.']);
}
if (strlen($newPass) < 6) {
    reply(400, ['success' => false, 'message' => 'New password must be at least 6 characters.']);
}
if ($newPass !== $confirm) {
    reply(400, ['success' => false, 'message' => 'New password and confirmation do not match.']);
}
if ($newPass === $current) {
    reply(400, ['success' => false, 'message' => 'New password must be different from your current password.']);
}

try {
    $customerId = (int)$_SESSION['customer_id'];

    $stmt = $conn->prepare(
        "SELECT c.customer_id, c.user_id, c.password AS customer_password, u.password AS user_password
         FROM `customer` AS c
         LEFT JOIN `user` AS u ON u.user_id = c.user_id
         WHERE c.customer_id = ?
         LIMIT 1"
    );
    if (!$stmt) {
        throw new RuntimeException('Could not prepare customer lookup.');
    }
    $stmt->bind_param('i', $customerId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        reply(404, ['success' => false, 'message' => 'Customer account not found. Please log in again.']);
    }

    $storedHash = (string)($row['user_password'] ?: $row['customer_password']);
    if ($storedHash === '' || (!password_verify($current, $storedHash) && $current !== $storedHash)) {
        reply(403, ['success' => false, 'message' => 'Current password is incorrect.']);
    }

    $newHash = password_hash($newPass, PASSWORD_DEFAULT);
    $userId = (int)($row['user_id'] ?? ($_SESSION['user_id'] ?? 0));

    $conn->begin_transaction();

    $updCust = $conn->prepare("UPDATE `customer` SET password = ? WHERE customer_id = ?");
    if (!$updCust) {
        throw new RuntimeException('Could not prepare customer password update.');
    }
    $updCust->bind_param('si', $newHash, $customerId);
    $updCust->execute();
    $updCust->close();

    if ($userId > 0) {
        $updUser = $conn->prepare("UPDATE `user` SET password = ? WHERE user_id = ?");
        if (!$updUser) {
            throw new RuntimeException('Could not prepare user password update.');
        }
        $updUser->bind_param('si', $newHash, $userId);
        $updUser->execute();
        $updUser->close();
    }

    $conn->commit();
    reply(200, ['success' => true, 'message' => 'Password updated successfully.']);
} catch (Throwable $e) {
    try { $conn->rollback(); } catch (Throwable $ignored) {}
    error_log('Customer change password error: ' . $e->getMessage());
    reply(500, ['success' => false, 'message' => 'Could not update password. Check the PHP error log.']);
}
?>
