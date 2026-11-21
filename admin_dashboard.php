<?php
session_start();
if (empty($_SESSION['admin_logged_in']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: admin_login.html');
    exit;
}
$htmlPath = __DIR__ . '/admin.html';
if (!is_file($htmlPath)) {
    http_response_code(500);
    exit('The existing admin.html dashboard could not be found.');
}
$html = file_get_contents($htmlPath);
if ($html === false) {
    http_response_code(500);
    exit('The administrator dashboard could not be loaded.');
}
$html = str_replace('reed (Admin)', htmlspecialchars($_SESSION['admin_name'] ?? 'Administrator', ENT_QUOTES, 'UTF-8') . ' (Admin)', $html);
$html = str_replace("onclick=\"alert('Logged out of Admin Portal')\"", 'href="admin_logout.php"', $html);
echo $html;
?>
