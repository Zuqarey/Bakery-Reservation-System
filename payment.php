<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db.php';
date_default_timezone_set('Asia/Kuala_Lumpur');

function respond($status, $body) {
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['success' => false, 'message' => 'Use POST to submit an order.']);
}

/* Only authenticated customers may place orders. */
if (empty($_SESSION['customer_logged_in']) || empty($_SESSION['customer_id'])) {
    respond(401, ['success' => false, 'message' => 'Please log in before placing an order.']);
}

$raw = isset($_POST['paymentData']) ? $_POST['paymentData'] : file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    respond(400, ['success' => false, 'message' => 'Invalid or empty order data.']);
}

$customerId = (int)$_SESSION['customer_id'];
$orderType = trim((string)($data['orderType'] ?? 'Self-Pickup'));
$outlet = trim((string)($data['outlet'] ?? ''));
$orderDate = trim((string)($data['orderDate'] ?? ''));
$orderTime = trim((string)($data['orderTime'] ?? ''));
$notes = trim((string)($data['specialInstructions'] ?? ''));
$paymentMethod = trim((string)($data['paymentMethod'] ?? 'Cash at Counter'));
$paymentStatus = trim((string)($data['paymentStatus'] ?? 'Pending Payment'));
$items = $data['items'] ?? [];

if (!in_array($orderType, ['Self-Pickup', 'Dine-In'], true)) {
    respond(422, ['success' => false, 'message' => 'Invalid order type.']);
}
if ($outlet === '' || $orderDate === '' || $orderTime === '') {
    respond(422, ['success' => false, 'message' => 'Please provide outlet, date and time.']);
}
if (!in_array($paymentMethod, ['QR Payment', 'Cash at Counter'], true)) {
    respond(422, ['success' => false, 'message' => 'Invalid payment method.']);
}
if (!in_array($paymentStatus, ['Pending Payment', 'Pending Verification'], true)) {
    $paymentStatus = 'Pending Payment';
}
if (!is_array($items) || count($items) < 1 || count($items) > 50) {
    respond(422, ['success' => false, 'message' => 'Your cart is empty or contains too many items.']);
}

$dt = DateTime::createFromFormat('!Y-m-d H:i', $orderDate . ' ' . $orderTime);
if (!$dt || $dt->format('Y-m-d H:i') !== $orderDate . ' ' . $orderTime ||
    $dt->format('Y-m-d H:i') <= date('Y-m-d H:i')) {
    respond(422, ['success' => false, 'message' => 'Please select a valid future pickup date and time.']);
}

try {
    /* Verify customer and take contact details from the account, not the browser. */
    $customerStmt = $conn->prepare('SELECT customer_id, full_name, email FROM customer WHERE customer_id = ? LIMIT 1');
    $customerStmt->bind_param('i', $customerId);
    $customerStmt->execute();
    $customer = $customerStmt->get_result()->fetch_assoc();
    if (!$customer) {
        respond(401, ['success' => false, 'message' => 'Customer account not found. Please log in again.']);
    }

    /* Resolve every cart item against the product table and use database prices.
       Custom cakes from customcake.html are mapped to an internal Custom Cake product row
       and their customization summary is appended to special_instructions for the kitchen. */
    $verifiedItems = [];
    $customCakeNotes = [];
    $total = 0.0;
    $productStmt = $conn->prepare('SELECT product_id, price, product_quantity FROM product WHERE product_name = ? AND is_active = 1 LIMIT 1');
    foreach ($items as $item) {
        $name = trim((string)($item['name'] ?? ''));
        $qty = filter_var($item['quantity'] ?? null, FILTER_VALIDATE_INT);
        $isCustomCake = (($item['type'] ?? '') === 'customcake')
            || (stripos($name, 'Custom ') === 0 && stripos($name, 'Cake') !== false);
        if ($name === '' || $qty === false || $qty < 1 || $qty > 99) {
            respond(422, ['success' => false, 'message' => 'A cart item has an invalid name or quantity.']);
        }
        $productStmt->bind_param('s', $name);
        $productStmt->execute();
        $product = $productStmt->get_result()->fetch_assoc();
        if (!$product && $isCustomCake) {
            $customPrice = filter_var($item['price'] ?? null, FILTER_VALIDATE_FLOAT);
            if ($customPrice === false || $customPrice < 10 || $customPrice > 5000) {
                respond(422, ['success' => false, 'message' => 'Invalid custom cake price.']);
            }
            $lookupCustom = $conn->prepare("SELECT product_id FROM product WHERE product_name = 'Custom Cake' LIMIT 1");
            $lookupCustom->execute();
            $customRow = $lookupCustom->get_result()->fetch_assoc();
            if ($customRow) {
                $customProductId = (int)$customRow['product_id'];
            } else {
                $createCustom = $conn->prepare("INSERT INTO product (product_name, price, product_quantity, category, description, image_path, is_active) VALUES ('Custom Cake', 0.00, 999, 'Custom Cake', 'Made-to-order custom cake', '', 0)");
                $createCustom->execute();
                $customProductId = (int)$conn->insert_id;
            }
            $details = trim((string)($item['details'] ?? ''));
            $customCakeNotes[] = $details !== '' ? ($name . ' (' . $details . ')') : $name;
            $verifiedItems[] = [
                'product_id' => $customProductId,
                'quantity' => $qty,
                'price' => (float)$customPrice,
                'skip_stock' => true
            ];
            $total += (float)$customPrice * $qty;
            continue;
        }
        if (!$product) {
            respond(422, ['success' => false, 'message' => 'Product "' . $name . '" was not found in the database.']);
        }
        if ((int)$product['product_quantity'] < $qty) {
            respond(409, ['success' => false, 'message' => 'Insufficient stock for "' . $name . '".']);
        }
        $price = (float)$product['price'];
        $verifiedItems[] = [
            'product_id' => (int)$product['product_id'],
            'quantity' => $qty,
            'price' => $price,
            'skip_stock' => false
        ];
        $total += $price * $qty;
    }

    if ($customCakeNotes) {
        $customNoteLine = 'Custom Cake: ' . implode('; ', $customCakeNotes);
        $notes = $notes !== '' ? ($notes . ' | ' . $customNoteLine) : $customNoteLine;
    }

    if ($total <= 0) {
        respond(422, ['success' => false, 'message' => 'Order total must be greater than zero.']);
    }

    $pickupDatetime = $dt->format('Y-m-d H:i:s');
    $conn->begin_transaction();

    $orderStmt = $conn->prepare("INSERT INTO orders
        (customer_id, staff_id, order_type, outlet, pickup_datetime, special_instructions, `date order`)
        VALUES (?, NULL, ?, ?, ?, ?, NOW())");
    $orderStmt->bind_param('issss', $customerId, $orderType, $outlet, $pickupDatetime, $notes);
    $orderStmt->execute();
    $orderId = (int)$conn->insert_id;

    $itemStmt = $conn->prepare('INSERT INTO `order item` (order_id, product_id, quantity, unit_price) VALUES (?, ?, ?, ?)');
    $stockStmt = $conn->prepare('UPDATE product SET product_quantity = product_quantity - ? WHERE product_id = ? AND product_quantity >= ?');
    foreach ($verifiedItems as $item) {
        $productId = $item['product_id'];
        $qty = $item['quantity'];
        $price = $item['price'];
        $itemStmt->bind_param('iiid', $orderId, $productId, $qty, $price);
        $itemStmt->execute();

        if (!empty($item['skip_stock'])) {
            continue;
        }
        $stockStmt->bind_param('iii', $qty, $productId, $qty);
        $stockStmt->execute();
        if ($stockStmt->affected_rows !== 1) {
            throw new RuntimeException('Stock changed while placing the order. Please try again.');
        }
    }

    $paymentStmt = $conn->prepare('INSERT INTO payment (order_id, total) VALUES (?, ?)');
    $paymentStmt->bind_param('id', $orderId, $total);
    $paymentStmt->execute();
    $referenceId = (int)$conn->insert_id;

    $methodStmt = $conn->prepare('INSERT INTO paymen_methode (reference_id, methode_name) VALUES (?, ?)');
    $methodStmt->bind_param('is', $referenceId, $paymentMethod);
    $methodStmt->execute();

    $receiptNo = 'REC-' . date('Ymd') . '-' . str_pad((string)$orderId, 4, '0', STR_PAD_LEFT);
    $logStmt = $conn->prepare('INSERT INTO order_log (reference_id, order_id, receipt_no, status_name) VALUES (?, ?, ?, ?)');
    $logStmt->bind_param('iiss', $referenceId, $orderId, $receiptNo, $paymentStatus);
    $logStmt->execute();

    $conn->commit();
    respond(201, [
        'success' => true,
        'orderId' => $orderId,
        'receiptNo' => $receiptNo,
        'total' => number_format($total, 2, '.', ''),
        'message' => 'Order saved successfully.'
    ]);
} catch (Throwable $e) {
    if ($conn->errno === 0) {
        // Transaction state is checked below; rollback is safe when a transaction is active.
    }
    try { $conn->rollback(); } catch (Throwable $ignored) {}
    error_log('Checkout/payment error: ' . $e->getMessage());
    respond(500, ['success' => false, 'message' => 'The order could not be saved. Check the PHP error log.']);
}
?>
