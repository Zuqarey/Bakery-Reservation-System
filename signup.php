<?php
session_start();
include 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $rawPassword = $_POST['password'] ?? '';

    if (empty($name) || empty($email) || empty($rawPassword)) {
        die("Please complete all registration fields. <a href='login.html'>Go back</a>");
    }

    // senang cite ni untuk cek kalau emel tu dah ada belum ornag pakai haaa mcm tu
    $check = $conn->prepare("SELECT user_id FROM user WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        die("Email is already registered. <a href='login.html'>Log in here</a>");
    }

    $hashedPassword = password_hash($rawPassword, PASSWORD_DEFAULT);

    // 1. masuk user db
    $stmtUser = $conn->prepare("INSERT INTO user (name, email, password) VALUES (?, ?, ?)");
    $stmtUser->bind_param("sss", $name, $email, $hashedPassword);

    if ($stmtUser->execute()) {
        $userId = $conn->insert_id;

        // 2. masuk db
        $stmtCust = $conn->prepare("INSERT INTO customer (full_name, email, password) VALUES (?, ?, ?)");
        $stmtCust->bind_param("sss", $name, $email, $hashedPassword);
        $stmtCust->execute();
        $customerId = $conn->insert_id;

        // record session
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        $_SESSION['customer_id'] = $customerId;
        $_SESSION['user_name'] = $name;
        $_SESSION['role'] = 'customer';

        header("Location: index.html");
        exit();
    } else {
        die("Ralat sistem: " . $stmtUser->error);
    }
}
?>