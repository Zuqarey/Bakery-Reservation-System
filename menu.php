
<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';

if (!isset($conn) || !$conn || $conn->connect_error) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database connection failed'
    ]);
    exit;
}

$sql = "SELECT
            product_id,
            product_name,
            price,
            product_quantity,
            category,
            description,
            image_path,
            available_branch
        FROM product
        WHERE is_active = 1
        ORDER BY product_id ASC";

$result = $conn->query($sql);

if (!$result) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Product query failed: ' . $conn->error
    ]);
    exit;
}

$products = [];

while ($row = $result->fetch_assoc()) {

    $products[] = [
        $row['product_name'],              // p[0]
        (float)$row['price'],              // p[1]
        $row['category'],                  // p[2]
        $row['image_path'],                // p[3]
        $row['description'],               // p[4]
        $row['available_branch'] ?: 'All'  // p[5]
    ];
}

echo json_encode(
    $products,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);

$conn->close();
?>
