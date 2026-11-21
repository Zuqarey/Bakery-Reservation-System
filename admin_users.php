<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
if (empty($_SESSION['admin_logged_in']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(401); echo json_encode(['success'=>false,'message'=>'Administrator login required.']); exit;
}
require_once __DIR__ . '/db.php';
function reply($code, $data) { http_response_code($code); echo json_encode($data); exit; }
$action = $_GET['action'] ?? '';
try {
 if ($action === 'list' && $_SERVER['REQUEST_METHOD'] === 'GET') {
   $staff=[];
   $res=$conn->query("SELECT s.staff_id,s.user_id,s.status,u.name,u.email FROM staff s INNER JOIN `user` u ON u.user_id=s.user_id ORDER BY u.name");
   while($r=$res->fetch_assoc()) $staff[]=$r;
   $customers=[];
   // Total payments per order first, then add up per customer.
   // (SUM(DISTINCT ...) was wrong: orders with the same amount were only counted once.)
   $sql="SELECT c.customer_id,c.full_name AS name,c.email,COUNT(o.order_id) AS orders,COALESCE(SUM(pt.order_total),0) AS spent
         FROM customer c
         LEFT JOIN orders o ON o.customer_id=c.customer_id
         LEFT JOIN (SELECT order_id, SUM(total) AS order_total FROM payment GROUP BY order_id) pt ON pt.order_id=o.order_id
         GROUP BY c.customer_id,c.full_name,c.email ORDER BY c.full_name";
   $res=$conn->query($sql);
   while($r=$res->fetch_assoc()) $customers[]=$r;
   reply(200,['success'=>true,'staff'=>$staff,'customers'=>$customers]);
 }
 if ($_SERVER['REQUEST_METHOD'] !== 'POST') reply(405,['success'=>false,'message'=>'POST required.']);
 $input=json_decode(file_get_contents('php://input'),true) ?: [];
 if ($action === 'create_staff') {
   $name=trim((string)($input['name']??'')); $email=strtolower(trim((string)($input['email']??''))); $password=(string)($input['password']??'');
   if($name==='' || mb_strlen($name)>100 || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>150 || strlen($password)<8) reply(400,['success'=>false,'message'=>'Provide a valid name, email and password of at least 8 characters.']);
   $conn->begin_transaction();
   $check=$conn->prepare("SELECT user_id FROM `user` WHERE email=? UNION SELECT user_id FROM admin WHERE user_id IN (SELECT user_id FROM `user` WHERE email=?) LIMIT 1");
   $check->bind_param('ss',$email,$email); $check->execute(); if($check->get_result()->num_rows) { $conn->rollback(); reply(409,['success'=>false,'message'=>'That email is already registered.']); }
   $hash=password_hash($password,PASSWORD_DEFAULT);
   $ins=$conn->prepare("INSERT INTO `user` (name,email,password) VALUES (?,?,?)"); $ins->bind_param('sss',$name,$email,$hash); $ins->execute(); $uid=$conn->insert_id;
   $staffIns=$conn->prepare("INSERT INTO staff (user_id,status) VALUES (?,'Active')"); $staffIns->bind_param('i',$uid); $staffIns->execute();
   $conn->commit(); reply(201,['success'=>true,'user_id'=>$uid,'staff_id'=>$conn->insert_id]);
 }
 if ($action === 'set_status') {
   $id=filter_var($input['staff_id']??null,FILTER_VALIDATE_INT); $status=$input['status']??'';
   if(!$id || !in_array($status,['Active','Inactive'],true)) reply(400,['success'=>false,'message'=>'Invalid staff status.']);
   $guard=$conn->prepare("SELECT staff_id FROM staff WHERE staff_id=? AND status='Removed'"); $guard->bind_param('i',$id); $guard->execute(); if($guard->get_result()->num_rows) reply(409,['success'=>false,'message'=>'Restore this staff account before changing its active status.']);
   $stmt=$conn->prepare('UPDATE staff SET status=? WHERE staff_id=?'); $stmt->bind_param('si',$status,$id); $stmt->execute();
   if($stmt->affected_rows===0) { $q=$conn->prepare('SELECT staff_id FROM staff WHERE staff_id=? AND status=?'); $q->bind_param('is',$id,$status); $q->execute(); if(!$q->get_result()->num_rows) reply(404,['success'=>false,'message'=>'Staff account not found.']); }
   reply(200,['success'=>true]);
 }
 if ($action === 'remove_staff' || $action === 'restore_staff') {
   $id=filter_var($input['staff_id']??null,FILTER_VALIDATE_INT);
   if(!$id || $id<1) reply(400,['success'=>false,'message'=>'Invalid staff ID.']);
   if($action==='remove_staff') {
     $stmt=$conn->prepare("UPDATE staff SET status='Removed' WHERE staff_id=? AND status<>'Removed'");
     $stmt->bind_param('i',$id); $stmt->execute();
     if($stmt->affected_rows!==1) { $q=$conn->prepare("SELECT staff_id FROM staff WHERE staff_id=? AND status='Removed'"); $q->bind_param('i',$id); $q->execute(); if(!$q->get_result()->num_rows) reply(404,['success'=>false,'message'=>'Staff account not found.']); }
     reply(200,['success'=>true,'message'=>'Staff member removed from the active directory.']);
   }
   $stmt=$conn->prepare("UPDATE staff SET status='Inactive' WHERE staff_id=? AND status='Removed'");
   $stmt->bind_param('i',$id); $stmt->execute();
   if($stmt->affected_rows!==1) reply(404,['success'=>false,'message'=>'Removed staff account not found.']);
   reply(200,['success'=>true,'message'=>'Staff member restored as Inactive. Activate the account when ready.']);
 }
 reply(400,['success'=>false,'message'=>'Unknown action.']);
} catch(Throwable $e) { if($conn->errno) $conn->rollback(); error_log('Admin users API: '.$e->getMessage()); reply(500,['success'=>false,'message'=>'Could not complete the staff/user operation. Check the PHP error log.']); }
