<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// This endpoint is for customer-facing pages only. An administrator session
// must never be reported as a customer login.
if (($_SESSION['role'] ?? '') === 'admin' || !empty($_SESSION['admin_logged_in'])) {
    echo json_encode(['loggedIn' => false]);
    exit;
}

if (!empty($_SESSION['customer_logged_in']) && ($_SESSION['role'] ?? 'customer') === 'customer') {
    $name = $_SESSION['customer_name'] ?? $_SESSION['full_name'] ?? $_SESSION['user_name'] ?? 'Customer';
    $email = $_SESSION['customer_email'] ?? $_SESSION['user_email'] ?? '';
    $id = $_SESSION['customer_id'] ?? $_SESSION['id'] ?? null;
    echo json_encode([
        'loggedIn' => true,
        'customer' => $name,
        'name' => $name,
        'email' => $email,
        'customer_id' => $id,
        'role' => 'customer'
    ]);
    exit;
}

echo json_encode(['loggedIn' => false]);
?>
