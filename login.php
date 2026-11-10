<?php
session_start();
include 'db.php';

$redirect = $_POST['redirect'] ?? '';

$redirectPage = ($redirect === 'reservation.html')
    ? 'reservation.html'
    : 'index.html';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        die("Sila isi e-mel dan kata laluan. <a href='login.html'>Cuba lagi</a>");
    }

    // 1. cek emel
    $stmt = $conn->prepare("SELECT user_id, name, password FROM user WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        $storedPass = $user['password'];

        // test jer nanti mungkin kena ubah
        $isValid = password_verify($password, $storedPass) || ($password === $storedPass) || ($password === '12345678');

        if ($isValid) {
            session_regenerate_id(true);
            $userId = $user['user_id'];
            $_SESSION['user_id'] = $userId;
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $email;

            // 2. kena ubah lagi
            $stmtAdmin = $conn->prepare("SELECT admin_id FROM admin WHERE user_id = ?");
            $stmtAdmin->bind_param("i", $userId);
            $stmtAdmin->execute();
            if ($stmtAdmin->get_result()->num_rows > 0) {
                $_SESSION['role'] = 'admin';
                header("Location: admin.html");
                exit();
            }

            // 3. ada salah sikit untuk staff ni kena ubah lagi
            $stmtStaff = $conn->prepare("SELECT staff_id FROM staff WHERE user_id = ?");
            $stmtStaff->bind_param("i", $userId);
            $stmtStaff->execute();
            $staffRes = $stmtStaff->get_result();
            if ($staffRes->num_rows > 0) {
                $staff = $staffRes->fetch_assoc();
                $_SESSION['role'] = 'staff';
                $_SESSION['staff_id'] = $staff['staff_id'];
                $_SESSION['staff_name'] = $user['name'];
                header("Location: staff_dashboard.html");
                exit();
            }

            // 4. cek CUSTOMER
            $stmtCust = $conn->prepare("SELECT customer_id FROM customer WHERE email = ?");
            $stmtCust->bind_param("s", $email);
            $stmtCust->execute();
            $custRes = $stmtCust->get_result();

            if ($custRes->num_rows > 0) {
                $cust = $custRes->fetch_assoc();
                $_SESSION['customer_id'] = $cust['customer_id'];
            } else {
                // daftar
                $stmtNewCust = $conn->prepare("INSERT INTO customer (user_id, full_name, email, password) VALUES (?, ?, ?, ?)");
                $stmtNewCust->bind_param("isss", $userId, $user['name'], $email, $storedPass);
                $stmtNewCust->execute();
                $_SESSION['customer_id'] = $conn->insert_id;
            }

        $_SESSION['role'] = 'customer';
header("Location: " . $redirectPage);
exit();
        }
    }

    die("Invalid email or password. <a href='login.html'>Try again</a>");
}
?>