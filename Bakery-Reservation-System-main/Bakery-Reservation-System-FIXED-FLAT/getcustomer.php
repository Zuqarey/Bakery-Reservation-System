<?php
session_start();
header('Content-Type: application/json');

if (!empty($_SESSION['user_email']) || !empty($_SESSION['customer_logged_in']) || !empty($_SESSION['user_id'])) {
    $name = $_SESSION['user_name'] ?? $_SESSION['customer_name'] ?? 'Customer';
    $email = $_SESSION['user_email'] ?? $_SESSION['customer_email'] ?? '';
    $id = $_SESSION['customer_id'] ?? $_SESSION['user_id'] ?? 1;

    echo json_encode([
        'loggedIn' => true,
        'customer' => $name, // Digunakan oleh menu.html
        'name' => $name,     // Digunakan oleh reservation.html
        'email' => $email,
        'customer_id' => $id,
        'role' => $_SESSION['role'] ?? 'customer'
    ]);
} else {
    echo json_encode([
        'loggedIn' => false
    ]);
}
?>