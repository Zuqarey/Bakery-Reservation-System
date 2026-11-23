<?php
session_start();
mysqli_report(MYSQLI_REPORT_OFF);

if (file_exists('db.php')) {
    include 'db.php';
}

if (!isset($conn) || !$conn || $conn->connect_error) {
    $conn = @new mysqli("localhost", "root", "", "bakery_reservation_system");
}

$redirect = $_POST['redirect'] ?? '';
$allowedRedirects = ['reservation.html', 'customcake.html', 'checkoutt.html', 'menu.html', 'custdashboard.html', 'index.html'];
$redirectPage = in_array($redirect, $allowedRedirects, true) ? $redirect : 'custdashboard.html';

// Simpan ke dalam KEDUA-DUA sessionStorage dan localStorage supaya semua halaman (menu, cart, checkout) kenal user!
function redirectWithSession($url, $name = '', $email = '', $custId = 1, $role = 'customer') {
    $jsUrl = json_encode($url, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    $jsName = json_encode($name, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    $jsEmail = json_encode($email, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    $safeId = (int)$custId;
    $jsIdStr = json_encode((string)$safeId);
    $jsRole = json_encode($role, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

    echo "<!DOCTYPE html><html><head><meta charset=\"utf-8\"><script>
        const userData = {
            id: $safeId,
            customer_id: $safeId,
            name: $jsName,
            email: $jsEmail,
            role: $jsRole,
            loggedIn: true
        };

        // Simpan dalam sessionStorage
        sessionStorage.setItem('cozyBakeDemoLoggedIn', 'true');
        sessionStorage.setItem('cozyBakeCustomerName', $jsName);
        sessionStorage.setItem('cozyBakeCustomerEmail', $jsEmail);
        sessionStorage.setItem('cozyBakeCustomerId', $jsIdStr);
        sessionStorage.setItem('isLoggedIn', 'true');
        sessionStorage.setItem('userEmail', $jsEmail);
        sessionStorage.setItem('userName', $jsName);
        sessionStorage.setItem('customer_id', $jsIdStr);
        sessionStorage.setItem('cozyBakeUser', JSON.stringify(userData));

        // Simpan juga dalam localStorage (jika menu.html / checkoutt.html menyemak localStorage)
        localStorage.setItem('cozyBakeDemoLoggedIn', 'true');
        localStorage.setItem('cozyBakeCustomerName', $jsName);
        localStorage.setItem('cozyBakeCustomerEmail', $jsEmail);
        localStorage.setItem('cozyBakeCustomerId', $jsIdStr);
        localStorage.setItem('isLoggedIn', 'true');
        localStorage.setItem('userEmail', $jsEmail);
        localStorage.setItem('userName', $jsName);
        localStorage.setItem('customer_id', $jsIdStr);
        localStorage.setItem('cozyBakeUser', JSON.stringify(userData));

        window.top.location.href = $jsUrl;
    </script></head><body>Redirecting...</body></html>";
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        echo "<script>alert('Sila isi e-mel dan kata laluan!'); window.history.back();</script>";
        exit();
    }

    // ADMIN: verify membership in the admin table, never infer role from email text.
    $adminStmt = $conn->prepare(
        "SELECT u.user_id, u.name, u.email, u.password
         FROM `user` AS u
         INNER JOIN `admin` AS a ON a.user_id = u.user_id
         WHERE LOWER(u.email) = ?
         LIMIT 1"
    );
    if (!$adminStmt) {
        error_log('Admin lookup prepare failed: ' . $conn->error);
        echo "<script>alert('Login could not be completed. Please check the server configuration.'); window.location.href='login.html';</script>";
        exit();
    }
    $adminStmt->bind_param('s', $email);
    $adminStmt->execute();
    $adminResult = $adminStmt->get_result();
    if ($adminResult && $adminResult->num_rows === 1) {
        $admin = $adminResult->fetch_assoc();
        if (!password_verify($password, $admin['password'])) {
            echo "<script>alert('Incorrect email or password.'); window.location.href='login.html';</script>";
            exit();
        }
        session_regenerate_id(true);
        $_SESSION = [];
        $_SESSION['role'] = 'admin';
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_id'] = (int)$admin['user_id'];
        $_SESSION['user_id'] = (int)$admin['user_id'];
        $_SESSION['admin_name'] = $admin['name'];
        $_SESSION['user_name'] = $admin['name'];
        $_SESSION['user_email'] = $admin['email'];
        header('Location: admin_dashboard.php');
        exit();
    }
    $adminStmt->close();

    $emailPrefix = explode('@', $email)[0];
    $cleanName = ucfirst(str_replace(['staff', 'admin', '_', '.', '-'], '', $emailPrefix));
    if (empty($cleanName)) $cleanName = 'Customer';

    // STAFF: authenticate against the staff membership in the database,
    // even when staff use the shared customer login screen.
    $staffStmt = $conn->prepare(
        "SELECT u.user_id, u.name, u.email, u.password, s.staff_id, s.status
         FROM `user` AS u
         INNER JOIN `staff` AS s ON s.user_id = u.user_id
         WHERE LOWER(u.email) = ?
         LIMIT 1"
    );
    if ($staffStmt) {
        $staffStmt->bind_param('s', $email);
        $staffStmt->execute();
        $staffResult = $staffStmt->get_result();
        if ($staffResult && $staffResult->num_rows === 1) {
            $staff = $staffResult->fetch_assoc();
            if (!password_verify($password, (string)$staff['password'])) {
                echo "<script>alert('Incorrect email or password.'); window.location.href='login.html';</script>";
                exit();
            }
            if (strcasecmp(trim((string)$staff['status']), 'Active') !== 0) {
                echo "<script>alert('Your staff account is not active. Please contact an administrator.'); window.location.href='login.html';</script>";
                exit();
            }
            session_regenerate_id(true);
            $_SESSION = [];
            $_SESSION['role'] = 'staff';
            $_SESSION['staff_logged_in'] = true;
            $_SESSION['staff_id'] = (int)$staff['staff_id'];
            $_SESSION['user_id'] = (int)$staff['user_id'];
            $_SESSION['staff_name'] = $staff['name'];
            $_SESSION['user_name'] = $staff['name'];
            $_SESSION['user_email'] = $staff['email'];
            redirectWithSession('staff_dashboard.php', $staff['name'], $staff['email'], (int)$staff['staff_id'], 'staff');
        }
        $staffStmt->close();
    }

    // 3. CUSTOMER
    $customerName = $cleanName;
    $customerId = 1;
    $userId = 1;

    if ($conn && !$conn->connect_error) {
        // Semak jadual user
        $stmt = $conn->prepare("SELECT user_id, name, password FROM `user` WHERE LOWER(email) = ?");
        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $res = $stmt->get_result();

            if ($res && $res->num_rows > 0) {
                $user = $res->fetch_assoc();
                $storedPass = (string)$user['password'];

                $validPassword = password_verify($password, $storedPass)
                    || (!str_starts_with($storedPass, '$') && hash_equals($storedPass, $password));

                if ($validPassword) {
                    $customerName = $user['name'];
                    $userId = (int)$user['user_id'];
                } else {
                    echo "<script>alert('Incorrect email or password.'); window.location.href='login.html';</script>";
                    exit();
                }
            } else {
                echo "<script>alert('No account found for that email. Please create an account first.'); window.location.href='login.html';</script>";
                exit();
            }
        }

        // Pastikan rekod wujud dalam jadual `customer` supaya ada customer_id yang sah untuk Cart & Orders!
        $stmtC = $conn->prepare("SELECT customer_id, full_name FROM `customer` WHERE LOWER(email) = ?");
        if ($stmtC) {
            $stmtC->bind_param("s", $email);
            $stmtC->execute();
            $cRes = $stmtC->get_result();
            if ($cRes && $cRes->num_rows > 0) {
                $cRow = $cRes->fetch_assoc();
                $customerId = (int)$cRow['customer_id'];
                if (!empty($cRow['full_name'])) {
                    $customerName = $cRow['full_name'];
                }
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmtNewC = $conn->prepare("INSERT INTO `customer` (`user_id`, `full_name`, `email`, `password`) VALUES (?, ?, ?, ?)");
                if ($stmtNewC) {
                    $stmtNewC->bind_param("isss", $userId, $customerName, $email, $hashed);
                    $stmtNewC->execute();
                    $customerId = (int)$conn->insert_id;
                }
            }
        }
    }

    // Reset session and set kesemua variasi nama session PHP yang mungkin disemak oleh fail cart/order rakan anda
    session_regenerate_id(true);
    $_SESSION = [];
    $_SESSION['logged_in'] = true;
    $_SESSION['customer_logged_in'] = true;
    $_SESSION['role'] = 'customer';
    $_SESSION['user_id'] = $userId;
    $_SESSION['customer_id'] = $customerId;
    $_SESSION['id'] = $customerId;
    $_SESSION['user_name'] = $customerName;
    $_SESSION['customer_name'] = $customerName;
    $_SESSION['full_name'] = $customerName;
    $_SESSION['name'] = $customerName;
    $_SESSION['user_email'] = $email;
    $_SESSION['customer_email'] = $email;
    $_SESSION['email'] = $email;

    redirectWithSession($redirectPage, $customerName, $email, $customerId, 'customer');
}
?>