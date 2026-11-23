<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
if (empty($_SESSION['staff_logged_in']) || empty($_SESSION['staff_id']) || ($_SESSION['role'] ?? '') !== 'staff') {
    http_response_code(401); echo json_encode(['success'=>false,'message'=>'Staff login required']); exit;
}
require_once __DIR__ . '/db.php';
$activeStaffCheck = $conn->prepare("SELECT status FROM staff WHERE staff_id = ? LIMIT 1");
$activeStaffId = (int)$_SESSION['staff_id'];
$activeStaffCheck->bind_param('i', $activeStaffId);
$activeStaffCheck->execute();
$activeStaffRow = $activeStaffCheck->get_result()->fetch_assoc();
if (!$activeStaffRow || strcasecmp(trim((string)$activeStaffRow['status']), 'Active') !== 0) {
    $_SESSION = [];
    session_destroy();
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'This staff account is no longer active. Please contact an administrator.']);
    exit;
}
function fail_json($message) { error_log('Staff ingredients API error: ' . $message); http_response_code(500); echo json_encode(['success'=>false,'message'=>'Could not load pantry and recipe data.']); exit; }
$ingredients=[]; $recipes=[];
$sql="SELECT ingredient_id, name, category, quantity, unit, status, status_text FROM ingredients ORDER BY ingredient_id";
$result=$conn->query($sql); if(!$result) fail_json($conn->error);
while($r=$result->fetch_assoc()) {
    $r['quantity_display']=rtrim(rtrim(number_format((float)$r['quantity'],3,'.',''),'0'),'.').' '.$r['unit'];
    $ingredients[]=$r;
}
$result=$conn->query("SELECT recipe_id, name, category, ingredients_count, prep_time FROM product_recipe ORDER BY recipe_id");
if(!$result) fail_json($conn->error);
while($r=$result->fetch_assoc()) $recipes[]=$r;
$stats=['total'=>count($ingredients),'low'=>0,'expiring'=>0,'expired'=>0,'recipe_count'=>count($recipes)];
foreach($ingredients as $r) { if($r['status']==='low')$stats['low']++; if($r['status']==='expiring')$stats['expiring']++; if($r['status']==='expired')$stats['expired']++; }
echo json_encode(['success'=>true,'ingredients'=>$ingredients,'recipes'=>$recipes,'stats'=>$stats]);
?>