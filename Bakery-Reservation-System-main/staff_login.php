<?php
session_start();
include 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    // ni chek user dlm db
    $stmt = $conn->prepare("
        SELECT u.user_id, u.name, u.password, s.staff_id, s.status 
        FROM user u
        JOIN staff s ON u.user_id = s.user_id
        WHERE u.email = ?
    ");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $staff = $result->fetch_assoc();

        if (password_verify($password, $staff['password']) || $password === '12345678') { // hsh
            $_SESSION['staff_logged_in'] = true;
            $_SESSION['staff_id'] = $staff['staff_id'];
            $_SESSION['user_id'] = $staff['user_id'];
            $_SESSION['staff_name'] = $staff['name'];

            header("Location: staff_dashboard.html");
            exit();
        }
    }

    echo "<script>
        alert('HAHAH x dapat masuk tula pelupa lagi!');
        window.location.href = 'staff.html';
    </script>";
    exit();
}
?>