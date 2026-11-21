<?php
session_start();
require_once __DIR__ . '/db.php';

function staffLoginFailure(string $message): void {
    $safe = json_encode($message, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    echo "<!doctype html><html><head><meta charset=\"utf-8\"></head><body><script>alert($safe); window.location.href='staff.html';</script></body></html>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: staff.html');
    exit;
}

$email = strtolower(trim($_POST['email'] ?? ''));
$password = $_POST['password'] ?? '';

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
    staffLoginFailure('Please enter your staff email and password.');
}

try {
    $stmt = $conn->prepare(
        "SELECT u.user_id, u.name, u.email, u.password, s.staff_id, s.status
         FROM `user` AS u
         INNER JOIN `staff` AS s ON s.user_id = u.user_id
         WHERE LOWER(u.email) = ?
         LIMIT 1"
    );
    if (!$stmt) {
        throw new RuntimeException('Could not prepare staff account lookup.');
    }

    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if (!$result || $result->num_rows !== 1) {
        staffLoginFailure('No staff account was found for that email.');
    }

    $staff = $result->fetch_assoc();

    // Staff accounts use the password_hash() format created by signup.php.
    if (!password_verify($password, $staff['password'])) {
        staffLoginFailure('Incorrect email or password.');
    }

    if (strcasecmp(trim((string)$staff['status']), 'Active') !== 0) {
        staffLoginFailure('Your staff account is not active. Please contact an administrator.');
    }

    session_regenerate_id(true);
    $_SESSION['staff_logged_in'] = true;
    $_SESSION['role'] = 'staff';
    $_SESSION['staff_id'] = (int)$staff['staff_id'];
    $_SESSION['user_id'] = (int)$staff['user_id'];
    $_SESSION['staff_name'] = $staff['name'];
    $_SESSION['user_name'] = $staff['name'];
    $_SESSION['user_email'] = $staff['email'];

    header('Location: staff_dashboard.php');
    exit;
} catch (Throwable $e) {
    error_log('Staff login error: ' . $e->getMessage());
    staffLoginFailure('Staff login could not be completed. Check the PHP error log.');
}
?>
