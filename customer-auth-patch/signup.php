<?php
session_start();
require_once __DIR__ . '/db.php';

function showMessage(string $message, string $target = 'login.html'): void {
    $safeMessage = json_encode($message, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    $safeTarget = json_encode($target, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    echo "<!doctype html><html><head><meta charset=\"utf-8\"></head><body><script>alert($safeMessage); window.location.href=$safeTarget;</script></body></html>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.html');
    exit;
}

$name = trim($_POST['name'] ?? '');
$email = strtolower(trim($_POST['email'] ?? ''));
$password = $_POST['password'] ?? '';
$redirect = $_POST['redirect'] ?? '';

if ($name === '' || $email === '' || $password === '') {
    showMessage('Please complete all registration fields.');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    showMessage('Please enter a valid email address.');
}
if (strlen($password) < 6) {
    showMessage('Password must contain at least 6 characters.');
}

// Public customer registration must never grant staff/admin privileges by email naming.
$emailPrefix = strtolower(explode('@', $email)[0]);
if (strpos($emailPrefix, 'admin') !== false || strpos($emailPrefix, 'staff') !== false) {
    showMessage('Please use a regular customer email address. Staff and admin accounts must be created by an authorized administrator.');
}

try {
    $conn->begin_transaction();

    $check = $conn->prepare('SELECT user_id FROM `user` WHERE LOWER(email) = ? LIMIT 1');
    $check->bind_param('s', $email);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $conn->rollback();
        showMessage('This email is already registered. Please log in instead.');
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $insertUser = $conn->prepare('INSERT INTO `user` (name, email, password) VALUES (?, ?, ?)');
    $insertUser->bind_param('sss', $name, $email, $hashedPassword);
    $insertUser->execute();
    $userId = (int)$conn->insert_id;

    $insertCustomer = $conn->prepare('INSERT INTO customer (user_id, full_name, email, password) VALUES (?, ?, ?, ?)');
    $insertCustomer->bind_param('isss', $userId, $name, $email, $hashedPassword);
    $insertCustomer->execute();
    $customerId = (int)$conn->insert_id;

    $conn->commit();

    $_SESSION['logged_in'] = true;
    $_SESSION['customer_logged_in'] = true;
    $_SESSION['role'] = 'customer';
    $_SESSION['user_id'] = $userId;
    $_SESSION['customer_id'] = $customerId;
    $_SESSION['id'] = $customerId;
    $_SESSION['user_name'] = $name;
    $_SESSION['customer_name'] = $name;
    $_SESSION['full_name'] = $name;
    $_SESSION['name'] = $name;
    $_SESSION['user_email'] = $email;
    $_SESSION['customer_email'] = $email;
    $_SESSION['email'] = $email;

    $target = ($redirect === 'reservation.html') ? 'reservation.html' : 'custdashboard.html';
    $userData = [
        'id' => $customerId,
        'customer_id' => $customerId,
        'user_id' => $userId,
        'name' => $name,
        'email' => $email,
        'role' => 'customer',
        'loggedIn' => true
    ];
    $json = json_encode($userData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    $targetJson = json_encode($target, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    echo "<!doctype html><html><head><meta charset=\"utf-8\"></head><body><script>
      const userData = $json;
      for (const store of [sessionStorage, localStorage]) {
        store.setItem('cozyBakeDemoLoggedIn', 'true');
        store.setItem('cozyBakeCustomerName', userData.name);
        store.setItem('cozyBakeCustomerEmail', userData.email);
        store.setItem('cozyBakeCustomerId', String(userData.customer_id));
        store.setItem('isLoggedIn', 'true');
        store.setItem('userEmail', userData.email);
        store.setItem('userName', userData.name);
        store.setItem('customer_id', String(userData.customer_id));
        store.setItem('cozyBakeUser', JSON.stringify(userData));
      }
      window.top.location.href = $targetJson;
    </script></body></html>";
    exit;
} catch (Throwable $e) {
    if ($conn->errno || $conn->connect_errno === 0) {
        try { $conn->rollback(); } catch (Throwable $ignored) {}
    }
    error_log('Customer signup failed: ' . $e->getMessage());
    showMessage('Registration could not be completed. Please check that the database tables are available and try again.');
}
