<?php
session_start();

header('Content-Type: application/json; charset=utf-8');

function respond(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

// =====================================
// ADMIN SECURITY CHECK
// =====================================

if (
    empty($_SESSION['admin_logged_in']) ||
    ($_SESSION['role'] ?? '') !== 'admin'
) {
    respond([
        'success' => false,
        'message' => 'Administrator login required.'
    ], 401);
}

// =====================================
// DATABASE CONNECTION
// =====================================

require_once __DIR__ . '/db.php';

if (!isset($conn) || !$conn || $conn->connect_error) {
    respond([
        'success' => false,
        'message' => 'Database connection unavailable.'
    ], 500);
}

try {

    // Check if the optional available_branch column exists so the catalog
    // still works before the ALTER TABLE migration is applied.
    $branchColCheck = $conn->query("SHOW COLUMNS FROM product LIKE 'available_branch'");
    $hasBranchColumn = ($branchColCheck && $branchColCheck->num_rows > 0);

    // =====================================
    // GET PRODUCTS
    // =====================================

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {

        $branchSelect = $hasBranchColumn ? "available_branch," : "";
        $result = $conn->query("
            SELECT
                product_id,
                product_name,
                price,
                product_quantity,
                category,
                description,
                image_path,
                {$branchSelect}
                is_active
            FROM product
            ORDER BY is_active DESC, product_name ASC
        ");

        if (!$result) {
            throw new RuntimeException(
                'Could not retrieve products.'
            );
        }

        $products = [];

        while ($row = $result->fetch_assoc()) {

            $row['product_id'] = (int)$row['product_id'];
            $row['price'] = (float)$row['price'];
            $row['product_quantity'] = (int)$row['product_quantity'];
            $row['is_active'] = (int)$row['is_active'];

            $row['available_branch'] =
                !empty($row['available_branch']) ? $row['available_branch'] : 'All';

            $products[] = $row;
        }

        respond([
            'success' => true,
            'products' => $products
        ]);
    }

    // =====================================
    // CHECK REQUEST METHOD
    // =====================================

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        respond([
            'success' => false,
            'message' => 'Method not allowed.'
        ], 405);
    }

    // =====================================
    // READ JSON INPUT
    // =====================================

    $input = json_decode(
        file_get_contents('php://input'),
        true
    );

    if (!is_array($input)) {
        respond([
            'success' => false,
            'message' => 'Invalid request body.'
        ], 400);
    }

    $action = $input['action'] ?? '';

    // =====================================
    // ADD / UPDATE PRODUCT
    // =====================================

    if ($action === 'save') {

        $name = trim((string)($input['product_name'] ?? ''));

        $category = trim(
            (string)($input['category'] ?? '')
        );

        $description = trim(
            (string)($input['description'] ?? '')
        );

        $image = trim(
            (string)($input['image_path'] ?? '')
        );

        $price = filter_var(
            $input['price'] ?? null,
            FILTER_VALIDATE_FLOAT
        );

        $stock = filter_var(
            $input['product_quantity'] ?? null,
            FILTER_VALIDATE_INT
        );

        $id = filter_var(
            $input['product_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        // =====================================
        // VALIDATE PRODUCT DETAILS
        // =====================================

        if (
            $name === '' ||
            mb_strlen($name) > 100 ||
            $category === '' ||
            mb_strlen($category) > 50 ||
            $price === false ||
            $price <= 0 ||
            $stock === false ||
            $stock < 0 ||
            mb_strlen($image) > 255
        ) {
            respond([
                'success' => false,
                'message' => 'Please enter a product name, category, valid price and non-negative stock quantity.'
            ], 400);
        }

        // =====================================
        // BRANCH AVAILABILITY
        // =====================================

        $branch = $input['available_branch'] ?? 'All';

        $allowedBranches = [
            'All',
            'Kedah',
            'Perlis',
            'Penang'
        ];

        if (!is_string($branch) ||
            !in_array($branch, $allowedBranches, true)) {

            respond([
                'success' => false,
                'message' => 'Invalid branch selection.'
            ], 400);
        }

        // =====================================
        // UPDATE EXISTING PRODUCT
        // =====================================

        if ($id) {

            if ($id < 1) {
                respond([
                    'success' => false,
                    'message' => 'Invalid product ID.'
                ], 400);
            }

            if ($hasBranchColumn) {
                $stmt = $conn->prepare("
                    UPDATE product
                    SET
                        product_name = ?,
                        category = ?,
                        price = ?,
                        product_quantity = ?,
                        description = ?,
                        image_path = ?,
                        available_branch = ?
                    WHERE product_id = ?
                ");

                if (!$stmt) {
                    throw new RuntimeException($conn->error);
                }

                $stmt->bind_param(
                    'ssdisssi',
                    $name,
                    $category,
                    $price,
                    $stock,
                    $description,
                    $image,
                    $branch,
                    $id
                );
            } else {
                $stmt = $conn->prepare("
                    UPDATE product
                    SET
                        product_name = ?,
                        category = ?,
                        price = ?,
                        product_quantity = ?,
                        description = ?,
                        image_path = ?
                    WHERE product_id = ?
                ");

                if (!$stmt) {
                    throw new RuntimeException($conn->error);
                }

                $stmt->bind_param(
                    'ssdissi',
                    $name,
                    $category,
                    $price,
                    $stock,
                    $description,
                    $image,
                    $id
                );
            }

            if (!$stmt->execute()) {
                throw new RuntimeException($stmt->error);
            }

            if ($stmt->affected_rows === 0) {

                $check = $conn->prepare("
                    SELECT product_id
                    FROM product
                    WHERE product_id = ?
                ");

                $check->bind_param('i', $id);
                $check->execute();

                if ($check->get_result()->num_rows === 0) {
                    respond([
                        'success' => false,
                        'message' => 'Product not found.'
                    ], 404);
                }
            }

            respond([
                'success' => true,
                'message' => 'Product updated successfully.'
            ]);
        }

        // =====================================
        // INSERT NEW PRODUCT
        // =====================================

        if ($hasBranchColumn) {
            $stmt = $conn->prepare("
                INSERT INTO product (
                    product_name,
                    category,
                    price,
                    product_quantity,
                    description,
                    image_path,
                    available_branch
                )
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            if (!$stmt) {
                throw new RuntimeException($conn->error);
            }

            $stmt->bind_param(
                'ssdisss',
                $name,
                $category,
                $price,
                $stock,
                $description,
                $image,
                $branch
            );
        } else {
            $stmt = $conn->prepare("
                INSERT INTO product (
                    product_name,
                    category,
                    price,
                    product_quantity,
                    description,
                    image_path
                )
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            if (!$stmt) {
                throw new RuntimeException($conn->error);
            }

            $stmt->bind_param(
                'ssdiss',
                $name,
                $category,
                $price,
                $stock,
                $description,
                $image
            );
        }

        if (!$stmt->execute()) {
            throw new RuntimeException($stmt->error);
        }

        respond([
            'success' => true,
            'message' => 'Product added successfully.',
            'product_id' => $conn->insert_id
        ], 201);
    }

    // =====================================
    // ARCHIVE / RESTORE PRODUCT
    // =====================================

    if (
        $action === 'archive' ||
        $action === 'restore'
    ) {

        $id = filter_var(
            $input['product_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$id || $id < 1) {
            respond([
                'success' => false,
                'message' => 'Invalid product ID.'
            ], 400);
        }

        $active = $action === 'restore' ? 1 : 0;

        $stmt = $conn->prepare("
            UPDATE product
            SET is_active = ?
            WHERE product_id = ?
        ");

        if (!$stmt) {
            throw new RuntimeException($conn->error);
        }

        $stmt->bind_param(
            'ii',
            $active,
            $id
        );

        if (!$stmt->execute()) {
            throw new RuntimeException($stmt->error);
        }

        if ($stmt->affected_rows === 0) {

            $check = $conn->prepare("
                SELECT product_id, is_active
                FROM product
                WHERE product_id = ?
            ");

            $check->bind_param('i', $id);
            $check->execute();

            $row = $check->get_result()->fetch_assoc();

            if (!$row) {
                respond([
                    'success' => false,
                    'message' => 'Product not found.'
                ], 404);
            }

            if ((int)$row['is_active'] !== $active) {
                respond([
                    'success' => false,
                    'message' => 'Product status could not be changed.'
                ], 409);
            }
        }

        respond([
            'success' => true,
            'message' => $active
                ? 'Product restored to the storefront.'
                : 'Product archived. Existing order history is preserved.'
        ]);
    }

    // =====================================
    // UNKNOWN ACTION
    // =====================================

    respond([
        'success' => false,
        'message' => 'Unknown catalog action.'
    ], 400);

} catch (Throwable $e) {

    error_log(
        'Admin catalog error: ' . $e->getMessage()
    );

    respond([
        'success' => false,
        'message' => 'Catalog operation failed. Check the XAMPP PHP error log.'
    ], 500);
}
?>
