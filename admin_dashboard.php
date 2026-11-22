<?php
session_start();
if (empty($_SESSION['admin_logged_in']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: login.html');
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
// Fill in the logged-in admin's name and avatar initial.
$adminName = trim((string)($_SESSION['admin_name'] ?? ''));
if ($adminName === '') {
    $adminName = 'Administrator';
}
$adminInitial = function_exists('mb_substr')
    ? mb_strtoupper(mb_substr($adminName, 0, 1, 'UTF-8'), 'UTF-8')
    : strtoupper(substr($adminName, 0, 1));
$html = str_replace(
    ['{{ADMIN_NAME}}', '{{ADMIN_INITIAL}}'],
    [htmlspecialchars($adminName, ENT_QUOTES, 'UTF-8'), htmlspecialchars($adminInitial, ENT_QUOTES, 'UTF-8')],
    $html
);
echo $html;
?>
