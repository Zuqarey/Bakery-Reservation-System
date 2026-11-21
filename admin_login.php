<?php
session_start();
require_once __DIR__ . '/db.php';

function adminLoginFailure(string $message): void {
    $safe = json_encode($message, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    echo "<!doctype html><html><head><meta charset=\"utf-8\"></head><body><script>alert($safe); window.location.href='admin_login.html';</script></body></html>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin_login.html');
    exit;
}

$email = strtolower(trim($_POST['email'] ?? ''));
$password = $_POST['password'] ?? '';
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
    adminLoginFailure('Please enter your administrator email and password.');
}

try {
    $stmt = $conn->prepare(
        "SELECT u.user_id, u.name, u.email, u.password
         FROM `user` AS u
         INNER JOIN `admin` AS a ON a.user_id = u.user_id
         WHERE LOWER(u.email) = ?
         LIMIT 1"
    );
    if (!$stmt) {
        throw new RuntimeException('Could not prepare administrator account lookup.');
    }
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    if (!$result || $result->num_rows !== 1) {
        adminLoginFailure('Incorrect administrator email or password.');
    }
    $admin = $result->fetch_assoc();
    if (!password_verify($password, $admin['password'])) {
        adminLoginFailure('Incorrect administrator email or password.');
    }

    session_regenerate_id(true);
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['role'] = 'admin';
    $_SESSION['admin_id'] = (int)$admin['user_id'];
    $_SESSION['user_id'] = (int)$admin['user_id'];
    $_SESSION['admin_name'] = $admin['name'];
    $_SESSION['user_name'] = $admin['name'];
    $_SESSION['user_email'] = $admin['email'];

    header('Location: admin_dashboard.php');
    exit;
} catch (Throwable $e) {
    error_log('Admin login error: ' . $e->getMessage());
    adminLoginFailure('Administrator login could not be completed. Check the PHP error log.');
}
?>
