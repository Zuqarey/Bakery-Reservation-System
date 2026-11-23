<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

function reply(int $code, array $data): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_SESSION['staff_logged_in']) || empty($_SESSION['staff_id']) || empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'staff') {
    reply(401, ['success' => false, 'message' => 'Please log in as staff.']);
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
if (strlen($newPass) < 8) {
    reply(400, ['success' => false, 'message' => 'New password must be at least 8 characters.']);
}
if ($newPass !== $confirm) {
    reply(400, ['success' => false, 'message' => 'New password and confirmation do not match.']);
}
if ($newPass === $current) {
    reply(400, ['success' => false, 'message' => 'New password must be different from your current password.']);
}

try {
    $staffId = (int)$_SESSION['staff_id'];
    $userId = (int)$_SESSION['user_id'];

    $stmt = $conn->prepare(
        "SELECT u.password, s.status
         FROM `user` AS u
         INNER JOIN `staff` AS s ON s.user_id = u.user_id
         WHERE s.staff_id = ? AND u.user_id = ?
         LIMIT 1"
    );
    if (!$stmt) {
        throw new RuntimeException('Could not prepare staff lookup.');
    }
    $stmt->bind_param('ii', $staffId, $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row || strcasecmp(trim((string)$row['status']), 'Active') !== 0) {
        $_SESSION = [];
        session_destroy();
        reply(401, ['success' => false, 'message' => 'This staff account is no longer active. Please contact an administrator.']);
    }

    if (!password_verify($current, (string)$row['password'])) {
        reply(403, ['success' => false, 'message' => 'Current password is incorrect.']);
    }

    $hash = password_hash($newPass, PASSWORD_DEFAULT);
    $upd = $conn->prepare("UPDATE `user` SET password = ? WHERE user_id = ?");
    if (!$upd) {
        throw new RuntimeException('Could not prepare password update.');
    }
    $upd->bind_param('si', $hash, $userId);
    $upd->execute();
    $upd->close();

    reply(200, ['success' => true, 'message' => 'Password updated successfully.']);
} catch (Throwable $e) {
    error_log('Staff change password error: ' . $e->getMessage());
    reply(500, ['success' => false, 'message' => 'Could not update password. Check the PHP error log.']);
}
?>
