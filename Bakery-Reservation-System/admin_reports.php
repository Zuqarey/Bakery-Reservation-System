<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
if (empty($_SESSION['admin_logged_in']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(401); echo json_encode(['success'=>false,'message'=>'Administrator login required.']); exit;
}
require_once __DIR__ . '/db.php';
date_default_timezone_set('Asia/Kuala_Lumpur');
try {
    $summarySql = "SELECT
      COALESCE(SUM(CASE WHEN DATE_FORMAT(o.`date order`, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m') THEN p.total ELSE 0 END),0) AS this_month,
      COALESCE(SUM(CASE WHEN DATE_FORMAT(o.`date order`, '%Y-%m') = DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), '%Y-%m') THEN p.total ELSE 0 END),0) AS last_month,
      COALESCE(SUM(p.total),0) AS all_time
      FROM payment p INNER JOIN orders o ON o.order_id=p.order_id";
    $summary = $conn->query($summarySql)->fetch_assoc();
    $months = [];
    $monthSql = "SELECT DATE_FORMAT(o.`date order`, '%b %Y') AS month, DATE_FORMAT(o.`date order`, '%Y-%m') AS sort_month, COALESCE(SUM(p.total),0) AS revenue
      FROM payment p INNER JOIN orders o ON o.order_id=p.order_id
      WHERE o.`date order` >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH)
      GROUP BY DATE_FORMAT(o.`date order`, '%Y-%m'), DATE_FORMAT(o.`date order`, '%b %Y')
      ORDER BY sort_month";
    $res=$conn->query($monthSql); while($row=$res->fetch_assoc()) { unset($row['sort_month']); $months[]=$row; }
    $ledgerSql = "SELECT o.order_id, o.`date order` AS order_date, o.outlet, o.order_type,
      COALESCE(c.full_name, 'Unknown customer') AS customer_name, p.total,
      ol.receipt_no, ol.status_name
      FROM orders o LEFT JOIN customer c ON c.customer_id=o.customer_id
      LEFT JOIN payment p ON p.order_id=o.order_id
      LEFT JOIN (SELECT l.order_id, l.receipt_no, l.status_name FROM order_log l INNER JOIN (SELECT order_id, MAX(order_log_id) AS latest_log_id FROM order_log GROUP BY order_id) latest ON latest.latest_log_id=l.order_log_id) ol ON ol.order_id=o.order_id
      ORDER BY o.`date order` DESC, o.order_id DESC LIMIT 200";
    $ledger=[]; $res=$conn->query($ledgerSql); while($row=$res->fetch_assoc()) $ledger[]=$row;
    echo json_encode(['success'=>true,'summary'=>$summary,'months'=>$months,'ledger'=>$ledger], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('Admin reports error: '.$e->getMessage());
    http_response_code(500); echo json_encode(['success'=>false,'message'=>'Could not load financial reports. Verify the existing table and column names.']);
}
