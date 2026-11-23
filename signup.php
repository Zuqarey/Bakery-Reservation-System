<?php
session_start();
include 'db.php';

function redirectWithCustomerSession($url, $name, $email, $custId) {
    $jsUrl = json_encode($url, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    $jsName = json_encode($name, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    $jsEmail = json_encode($email, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    $safeId = (int)$custId;
    $jsIdStr = json_encode((string)$safeId);

    echo "<!DOCTYPE html><html><head><meta charset=\"utf-8\"><script>
        const userData = {
            id: $safeId,
            customer_id: $safeId,
            name: $jsName,
            email: $jsEmail,
            role: 'customer',
            loggedIn: true
        };
        [sessionStorage, localStorage].forEach(store => {
            store.setItem('cozyBakeDemoLoggedIn', 'true');
            store.setItem('cozyBakeCustomerName', $jsName);
            store.setItem('cozyBakeCustomerEmail', $jsEmail);
            store.setItem('cozyBakeCustomerId', $jsIdStr);
            store.setItem('isLoggedIn', 'true');
            store.setItem('userEmail', $jsEmail);
            store.setItem('userName', $jsName);
            store.setItem('customer_id', $jsIdStr);
            store.setItem('cozyBakeUser', JSON.stringify(userData));
        });
        window.top.location.href = $jsUrl;
    </script></head><body>Redirecting...</body></html>";
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = trim($_POST['password'] ?? '');
    $redirect = $_POST['redirect'] ?? '';
    $allowedRedirects = ['reservation.html', 'customcake.html', 'checkoutt.html', 'menu.html', 'custdashboard.html', 'index.html'];
    $redirectPage = in_array($redirect, $allowedRedirects, true) ? $redirect : 'custdashboard.html';

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
    $newUserId = (int)$conn->insert_id;

    // 2. Public registration always creates a customer account (staff/admin are managed in the Admin Portal)
    $stmtCust = $conn->prepare("INSERT INTO customer (user_id, full_name, email, password) VALUES (?, ?, ?, ?)");
    $stmtCust->bind_param("isss", $newUserId, $name, $email, $hashedPass);
    if (!$stmtCust->execute()) {
        die("Ralat pendaftaran customer: " . $conn->error);
    }
    $customerId = (int)$conn->insert_id;

    session_regenerate_id(true);
    $_SESSION = [];
    $_SESSION['logged_in'] = true;
    $_SESSION['customer_logged_in'] = true;
    $_SESSION['role'] = 'customer';
    $_SESSION['user_id'] = $newUserId;
    $_SESSION['customer_id'] = $customerId;
    $_SESSION['id'] = $customerId;
    $_SESSION['user_name'] = $name;
    $_SESSION['customer_name'] = $name;
    $_SESSION['full_name'] = $name;
    $_SESSION['name'] = $name;
    $_SESSION['user_email'] = $email;
    $_SESSION['customer_email'] = $email;
    $_SESSION['email'] = $email;

    redirectWithCustomerSession($redirectPage, $name, $email, $customerId);
}
?>