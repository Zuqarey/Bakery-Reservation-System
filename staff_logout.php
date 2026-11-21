<?php
session_start();
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}
session_destroy();
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><title>Logging out</title></head>
<body>
<script>
  localStorage.clear();
  sessionStorage.clear();
  window.location.replace('index.html?logout=1');
</script>
<noscript><a href="index.html?logout=1">Return to Cozy Bake homepage</a></noscript>
</body>
</html>
