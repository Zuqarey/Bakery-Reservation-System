<?php
session_start();
$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

session_destroy();

// Cari fail front page (index.html atau index.php)
$target = file_exists(__DIR__ . '/index.php') ? 'index.php' : 'index.html';
?>
<!DOCTYPE html>
<html>
<head>
    <script>
        // Padam SEMUA data login dalam browser sebelum balik ke index
        localStorage.clear();
        sessionStorage.clear();
        window.location.replace("<?php echo $target; ?>?logout=1");
    </script>
</head>
<body></body>
</html>