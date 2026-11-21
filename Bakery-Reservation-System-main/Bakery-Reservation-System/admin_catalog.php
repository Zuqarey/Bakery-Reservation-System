<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
function respond(array $data, int $status=200): void { http_response_code($status); echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
if (empty($_SESSION['admin_logged_in']) || ($_SESSION['role'] ?? '') !== 'admin') respond(['success'=>false,'message'=>'Administrator login required.'],401);
require_once __DIR__ . '/db.php';
if (!isset($conn) || $conn->connect_error) respond(['success'=>false,'message'=>'Database connection unavailable.'],500);
try {
 if ($_SERVER['REQUEST_METHOD']==='GET') {
  $result=$conn->query('SELECT product_id, product_name, price, product_quantity, category, description, image_path, is_active FROM product ORDER BY is_active DESC, product_name ASC');
  if (!$result) throw new RuntimeException('Could not retrieve products.');
  $products=[]; while($row=$result->fetch_assoc()){ $row['product_id']=(int)$row['product_id']; $row['price']=(float)$row['price']; $row['product_quantity']=(int)$row['product_quantity']; $row['is_active']=(int)$row['is_active']; $products[]=$row; }
  respond(['success'=>true,'products'=>$products]);
 }
 if ($_SERVER['REQUEST_METHOD']!=='POST') respond(['success'=>false,'message'=>'Method not allowed.'],405);
 $input=json_decode(file_get_contents('php://input'),true); if(!is_array($input)) respond(['success'=>false,'message'=>'Invalid request body.'],400);
 $action=$input['action']??'';
 if($action==='save'){
  $name=trim((string)($input['product_name']??''));$category=trim((string)($input['category']??''));$description=trim((string)($input['description']??''));$image=trim((string)($input['image_path']??''));
  $price=filter_var($input['price']??null,FILTER_VALIDATE_FLOAT);$stock=filter_var($input['product_quantity']??null,FILTER_VALIDATE_INT);$id=filter_var($input['product_id']??null,FILTER_VALIDATE_INT);
  if($name===''||mb_strlen($name)>100||$category===''||mb_strlen($category)>50||$price===false||$price<=0||$stock===false||$stock<0||mb_strlen($image)>255) respond(['success'=>false,'message'=>'Please enter a product name, category, valid price and non-negative stock quantity.'],400);
  if($id){$stmt=$conn->prepare('UPDATE product SET product_name=?, category=?, price=?, product_quantity=?, description=?, image_path=? WHERE product_id=?');$stmt->bind_param('ssdissi',$name,$category,$price,$stock,$description,$image,$id);$stmt->execute();if($stmt->affected_rows===0){$check=$conn->prepare('SELECT product_id FROM product WHERE product_id=?');$check->bind_param('i',$id);$check->execute();if($check->get_result()->num_rows===0)respond(['success'=>false,'message'=>'Product not found.'],404);}respond(['success'=>true,'message'=>'Product updated.']);}
  $stmt=$conn->prepare('INSERT INTO product (product_name, category, price, product_quantity, description, image_path, is_active) VALUES (?,?,?,?,?,?,1)');$stmt->bind_param('ssdiss',$name,$category,$price,$stock,$description,$image);$stmt->execute();respond(['success'=>true,'product_id'=>$conn->insert_id],201);
 }
 if($action==='delete' || $action==='restore'){
  $id=filter_var($input['product_id']??null,FILTER_VALIDATE_INT);if(!$id||$id<1)respond(['success'=>false,'message'=>'Invalid product ID.'],400);
  $active=$action==='restore'?1:0;
  $stmt=$conn->prepare('UPDATE product SET is_active=? WHERE product_id=?');$stmt->bind_param('ii',$active,$id);$stmt->execute();
  if($stmt->affected_rows===0){$check=$conn->prepare('SELECT product_id FROM product WHERE product_id=?');$check->bind_param('i',$id);$check->execute();if(!$check->get_result()->num_rows)respond(['success'=>false,'message'=>'Product not found.'],404);}
  respond(['success'=>true,'message'=>$active?'Product restored to storefront.':'Product archived.']);
 }
 respond(['success'=>false,'message'=>'Unknown catalog action.'],400);
} catch(Throwable $e){error_log('Admin catalog error: '.$e->getMessage());respond(['success'=>false,'message'=>'Catalog operation failed. Check the XAMPP PHP error log.'],500);}
?>