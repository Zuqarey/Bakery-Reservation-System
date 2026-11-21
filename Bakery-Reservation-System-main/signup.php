<?php
session_start();
include 'db.php';

function redirectTop($url) {
    echo "<script>window.top.location.href = '" . htmlspecialchars($url, ENT_QUOTES) . "';</script>";
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = trim($_POST['password'] ?? '');
    $redirect = $_POST['redirect'] ?? '';
    $redirectPage = ($redirect === 'reservation.html') ? 'reservation.html' : 'index.html';

    if (empty($name) || empty($email) || empty($password)) {
        echo "<script>alert('Sila isi nama, e-mel, dan kata laluan.'); window.history.back();</script>";
        exit();
    }

    // Semak jika e-mel sudah wujud dalam jadual `user`
    $check = $conn->prepare("SELECT user_id FROM user WHERE LOWER(email) = ?");
    $check->bind_param("s", $email);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        echo "<script>alert('E-mel ini telah berdaftar! Sila log masuk.'); window.location.href = 'login.html';</script>";
        exit();
    }

    $hashedPass = password_hash($password, PASSWORD_DEFAULT);

    // 1. Simpan ke jadual `user`
    $stmtUser = $conn->prepare("INSERT INTO user (name, email, password) VALUES (?, ?, ?)");
    $stmtUser->bind_param("sss", $name, $email, $hashedPass);
    if (!$stmtUser->execute()) {
        die("Ralat pendaftaran user: " . $conn->error);
    }
    $newUserId = $conn->insert_id;

    $_SESSION['user_id'] = $newUserId;
    $_SESSION['user_name'] = $name;
    $_SESSION['user_email'] = $email;

    // Semak bahagian sebelum '@' untuk perkataan 'admin' atau 'staff'
    $emailPrefix = explode('@', $email)[0];
    $isAdminEmail = (strpos($emailPrefix, 'admin') !== false);
    $isStaffEmail = (strpos($emailPrefix, 'staff') !== false);

    // 2. Agihkan mengikut kandungan emel
    if ($isAdminEmail) {
        $stmtAdmin = $conn->prepare("INSERT INTO admin (user_id) VALUES (?)");
        $stmtAdmin->bind_param("i", $newUserId);
        $stmtAdmin->execute();
        $_SESSION['role'] = 'admin';
        $_SESSION['admin_id'] = $conn->insert_id;
        $_SESSION['admin_logged_in'] = true;
        redirectTop("admin.html");

    } elseif ($isStaffEmail) {
        $stmtStaff = $conn->prepare("INSERT INTO staff (user_id, status) VALUES (?, 'Active')");
        $stmtStaff->bind_param("i", $newUserId);
        $stmtStaff->execute();
        $staffId = $conn->insert_id;

        $_SESSION['role'] = 'staff';
        $_SESSION['staff_logged_in'] = true;
        $_SESSION['staff_id'] = $staffId;
        $_SESSION['staff_name'] = $name;
        redirectTop("staff_dashboard.html");

    } else {
        $stmtCust = $conn->prepare("INSERT INTO customer (user_id, full_name, email, password) VALUES (?, ?, ?, ?)");
        $stmtCust->bind_param("isss", $newUserId, $name, $email, $hashedPass);
        $stmtCust->execute();
        $_SESSION['role'] = 'customer';
        $_SESSION['customer_id'] = $conn->insert_id;
        redirectTop($redirectPage);
    }
}
?>