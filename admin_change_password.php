<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

function reply(int $code, array $data): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_SESSION['admin_logged_in']) || ($_SESSION['role'] ?? '') !== 'admin' || empty($_SESSION['user_id'])) {
    reply(401, ['success' => false, 'message' => 'Administrator login required.']);
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
    $userId = (int)$_SESSION['user_id'];
    $stmt = $conn->prepare(
        "SELECT u.password
         FROM `user` AS u
         INNER JOIN `admin` AS a ON a.user_id = u.user_id
         WHERE u.user_id = ?
         LIMIT 1"
    );
    if (!$stmt) {
        throw new RuntimeException('Could not prepare admin lookup.');
    }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row || !password_verify($current, (string)$row['password'])) {
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
    error_log('Admin change password error: ' . $e->getMessage());
    reply(500, ['success' => false, 'message' => 'Could not update password. Check the PHP error log.']);
}
?>
