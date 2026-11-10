
<?php
session_start();
header("Content-Type: application/json");

if (
    isset($_SESSION['user_id']) &&
    ($_SESSION['role'] ?? '') === 'customer'
) {
    echo json_encode([
        "loggedIn" => true,
        "role" => "customer",
        "customer" => [
            "name" => $_SESSION['user_name'] ?? "",
            "email" => $_SESSION['user_email'] ?? ""
        ]
    ]);
} else {
    echo json_encode([
        "loggedIn" => false,
        "role" => "guest"
    ]);
}
?>
