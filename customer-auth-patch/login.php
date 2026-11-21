<?php
session_start();
require_once __DIR__ . '/db.php';

function loginFailure(string $message): void {
    $safeMessage = json_encode($message, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    echo "<!doctype html><html><head><meta charset=\"utf-8\"></head><body><script>alert($safeMessage); window.location.href='login.html';</script></body></html>";
    exit;
}

function completeLogin(string $target, array $userData): void {
    $json = json_encode($userData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    $targetJson = json_encode($target, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    echo "<!doctype html><html><head><meta charset=\"utf-8\"></head><body><script>
      const userData = $json;
      for (const store of [sessionStorage, localStorage]) {
        store.setItem('cozyBakeDemoLoggedIn', 'true');
        store.setItem('cozyBakeCustomerName', userData.name);
        store.setItem('cozyBakeCustomerEmail', userData.email);
        store.setItem('cozyBakeCustomerId', String(userData.customer_id || userData.user_id));
        store.setItem('isLoggedIn', 'true');
        store.setItem('userEmail', userData.email);
        store.setItem('userName', userData.name);
        store.setItem('customer_id', String(userData.customer_id || userData.user_id));
        store.setItem('cozyBakeUser', JSON.stringify(userData));
      }
      window.top.location.href = $targetJson;
    </script></body></html>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.html');
    exit;
}

$email = strtolower(trim($_POST['email'] ?? ''));
$password = $_POST['password'] ?? '';
$redirect = $_POST['redirect'] ?? '';

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
    loginFailure('Please enter a valid email address and password.');
}

try {
    // Role membership comes from the database, never from the text in an email address.
    $stmt = $conn->prepare("SELECT u.user_id, u.name, u.email, u.password,
                                   c.customer_id, a.user_id AS admin_user_id,
                                   s.staff_id, s.status AS staff_status
                            FROM `user` u
                            LEFT JOIN customer c ON c.user_id = u.user_id
                            LEFT JOIN admin a ON a.user_id = u.user_id
                            LEFT JOIN staff s ON s.user_id = u.user_id
                            WHERE LOWER(u.email) = ?
                            LIMIT 1");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows !== 1) {
        loginFailure('No account was found for that email. Please register first.');
    }

    $account = $result->fetch_assoc();
    if (!password_verify($password, $account['password'])) {
        loginFailure('Incorrect email or password. Please try again.');
    }

    $userId = (int)$account['user_id'];
    $name = $account['name'];
    $role = 'customer';
    $customerId = (int)($account['customer_id'] ?? 0);

    if ($account['admin_user_id'] !== null) {
        $role = 'admin';
        $_SESSION['role'] = 'admin';
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_name'] = $name;
        $_SESSION['user_email'] = $email;
        completeLogin('admin.html', [
            'id' => $userId, 'user_id' => $userId, 'customer_id' => $userId,
            'name' => $name, 'email' => $email, 'role' => $role, 'loggedIn' => true
        ]);
    }

    if ($account['staff_id'] !== null) {
        if (strcasecmp((string)$account['staff_status'], 'Active') !== 0) {
            loginFailure('This staff account is not active. Please contact an administrator.');
        }
        $role = 'staff';
        $_SESSION['role'] = 'staff';
        $_SESSION['staff_logged_in'] = true;
        $_SESSION['staff_id'] = (int)$account['staff_id'];
        $_SESSION['user_id'] = $userId;
        $_SESSION['staff_name'] = $name;
        $_SESSION['user_name'] = $name;
        $_SESSION['user_email'] = $email;
        completeLogin('staff_dashboard.html', [
            'id' => (int)$account['staff_id'], 'user_id' => $userId, 'customer_id' => (int)$account['staff_id'],
            'name' => $name, 'email' => $email, 'role' => $role, 'loggedIn' => true
        ]);
    }

    // Repair a missing customer profile only after the user has supplied the correct password.
    if ($customerId === 0) {
        $findCustomer = $conn->prepare('SELECT customer_id FROM customer WHERE LOWER(email) = ? LIMIT 1');
        $findCustomer->bind_param('s', $email);
        $findCustomer->execute();
        $existing = $findCustomer->get_result()->fetch_assoc();
        if ($existing) {
            $customerId = (int)$existing['customer_id'];
        } else {
            $createCustomer = $conn->prepare('INSERT INTO customer (user_id, full_name, email, password) VALUES (?, ?, ?, ?)');
            $createCustomer->bind_param('isss', $userId, $name, $email, $account['password']);
            $createCustomer->execute();
            $customerId = (int)$conn->insert_id;
        }
    }

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
    completeLogin($target, [
        'id' => $customerId, 'customer_id' => $customerId, 'user_id' => $userId,
        'name' => $name, 'email' => $email, 'role' => 'customer', 'loggedIn' => true
    ]);
} catch (Throwable $e) {
    error_log('Customer login failed: ' . $e->getMessage());
    loginFailure('Login could not be completed because of a database error. Please check the PHP error log.');
}
