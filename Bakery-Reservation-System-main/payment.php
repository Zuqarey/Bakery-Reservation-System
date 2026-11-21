<?php
session_start();
header('Content-Type: application/json');
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Kaedah permintaan tidak sah.']);
    exit();
}

// dah dapat x nak pulang...pulang lah balik ni untuk chek gak
$raw = isset($_POST['paymentData']) ? $_POST['paymentData'] : file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Data pesanan tidak sah atau kosong.']);
    exit();
}

$customerName   = trim($data['customerName'] ?? '');
$customerPhone  = trim($data['customerPhone'] ?? '');
$customerEmail  = trim($data['customerEmail'] ?? '');
$orderType      = $data['orderType'] ?? 'Self-Pickup';
$outlet         = $data['outlet'] ?? '';
$orderDate      = $data['orderDate'] ?? date('Y-m-d');
$orderTime      = $data['orderTime'] ?? date('H:i');
$specialNotes   = $data['specialInstructions'] ?? '';
$paymentMethod  = $data['paymentMethod'] ?? 'Cash at Counter';
$paymentStatus  = $data['paymentStatus'] ?? 'Pending Payment';
$items          = $data['items'] ?? [];
$total          = floatval($data['total'] ?? 0);

if (empty($customerEmail) || empty($items)) {
    echo json_encode(['success' => false, 'message' => 'Maklumat pelanggan atau troli tidak lengkap.']);
    exit();
}

$conn->begin_transaction();

try {
    // 1. cek dalam db ade ark cus
    $stmtCust = $conn->prepare("SELECT customer_id FROM customer WHERE email = ?");
    $stmtCust->bind_param("s", $customerEmail);
    $stmtCust->execute();
    $resCust = $stmtCust->get_result();

    if ($resCust->num_rows > 0) {
        $customer = $resCust->fetch_assoc();
        $customerId = $customer['customer_id'];
    } else {
        // security jer ni
        $dummyPassword = password_hash(bin2hex(random_bytes(6)), PASSWORD_DEFAULT);
        $stmtNewCust = $conn->prepare("INSERT INTO customer (full_name, email, password) VALUES (?, ?, ?)");
        $stmtNewCust->bind_param("sss", $customerName, $customerEmail, $dummyPassword);
        $stmtNewCust->execute();
        $customerId = $conn->insert_id;
    }

    // 2. tarikh and masa
    $pickupDatetime = date('Y-m-d H:i:s', strtotime("$orderDate $orderTime"));

    // 3. insert dlm db
    $stmtOrder = $conn->prepare("INSERT INTO orders (customer_id, staff_id, order_type, outlet, pickup_datetime, special_instructions, `date order`) VALUES (?, NULL, ?, ?, ?, ?, NOW())");
    $stmtOrder->bind_param("issss", $customerId, $orderType, $outlet, $pickupDatetime, $specialNotes);
    $stmtOrder->execute();
    $orderId = $conn->insert_id;

    // 4. sama masuk db
    $stmtItem = $conn->prepare("INSERT INTO `order item` (order_id, product_id, quantity, unit_price) VALUES (?, ?, ?, ?)");

    foreach ($items as $item) {
        $prodName = $item['name'];
        $qty = intval($item['quantity']);
        $unitPrice = floatval($item['price']);

        // nk cari produck
        $stmtProd = $conn->prepare("SELECT product_id FROM product WHERE product_name = ?");
        $stmtProd->bind_param("s", $prodName);
        $stmtProd->execute();
        $resProd = $stmtProd->get_result();

        if ($resProd->num_rows > 0) {
            $prod = $resProd->fetch_assoc();
            $productId = $prod['product_id'];
        } else {
            // xde produck kita adakan kita buat dia bukan dia buat kita
            $stmtNewProd = $conn->prepare("INSERT INTO product (product_name, price, product_quantity) VALUES (?, ?, 50)");
            $stmtNewProd->bind_param("sd", $prodName, $unitPrice);
            $stmtNewProd->execute();
            $productId = $conn->insert_id;
        }

        $stmtItem->bind_param("iiid", $orderId, $productId, $qty, $unitPrice);
        $stmtItem->execute();
    }

    // 5. masuk db payment
    $stmtPay = $conn->prepare("INSERT INTO payment (order_id, total) VALUES (?, ?)");
    $stmtPay->bind_param("id", $orderId, $total);
    $stmtPay->execute();
    $referenceId = $conn->insert_id;

    // 6. payment m
    $stmtMethode = $conn->prepare("INSERT INTO paymen_methode (reference_id, methode_name) VALUES (?, ?)");
    $stmtMethode->bind_param("is", $referenceId, $paymentMethod);
    $stmtMethode->execute();

    // 7. masuk ker order ker order log x tau jadi ark
    $receiptNo = "REC-" . date('Ymd') . "-" . str_pad($orderId, 4, '0', STR_PAD_LEFT);
    $stmtLog = $conn->prepare("INSERT INTO order_log (reference_id, order_id, receipt_no, status_name) VALUES (?, ?, ?, ?)");
    $stmtLog->bind_param("iiss", $referenceId, $orderId, $receiptNo, $paymentStatus);
    $stmtLog->execute();

    $conn->commit();

    echo json_encode([
        'success'   => true,
        'orderId'   => $orderId,
        'receiptNo' => $receiptNo,
        'message'   => 'Pesanan berjaya didaftarkan ke pangkalan data.'
    ]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'success' => false,
        'message' => 'Ralat pangkalan data: ' . $e->getMessage()
    ]);
}
?>