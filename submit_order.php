<?php
require_once 'config.php';
require_once 'auth.php';

require_role(['kitchen_staff', 'admin']);

$input = json_decode(file_get_contents('php://input'), true);
$supplier_id = isset($input['supplier_id']) ? intval($input['supplier_id']) : 0;
$delivery_date = isset($input['delivery_date']) ? trim($input['delivery_date']) : '';
$notes = isset($input['notes']) ? trim($input['notes']) : '';
$items = isset($input['items']) && is_array($input['items']) ? $input['items'] : [];

if ($supplier_id <= 0 || $delivery_date === '' || count($items) === 0) {
    echo json_encode(["status" => "error", "message" => "Invalid order payload"]);
    exit();
}

try {
    $conn->beginTransaction();

    $conn->exec("CREATE TABLE IF NOT EXISTS suppliers (
        id INT PRIMARY KEY AUTO_INCREMENT,
        supplier_name VARCHAR(150) NOT NULL,
        contact_person VARCHAR(100) NULL,
        phone VARCHAR(30) NULL,
        email VARCHAR(120) NULL,
        address VARCHAR(255) NULL,
        active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->exec("CREATE TABLE IF NOT EXISTS purchase_orders (
        id INT PRIMARY KEY AUTO_INCREMENT,
        supplier_id INT NOT NULL,
        order_date DATE NOT NULL,
        delivery_date DATE NOT NULL,
        status ENUM('pending','confirmed','delivered','cancelled') DEFAULT 'pending',
        notes TEXT NULL,
        created_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE RESTRICT,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->exec("CREATE TABLE IF NOT EXISTS purchase_order_items (
        id INT PRIMARY KEY AUTO_INCREMENT,
        order_id INT NOT NULL,
        ingredient_name VARCHAR(120) NOT NULL,
        quantity DECIMAL(12,2) NOT NULL,
        unit VARCHAR(20) NOT NULL DEFAULT 'kg',
        FOREIGN KEY (order_id) REFERENCES purchase_orders(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $stmt = $conn->prepare("INSERT INTO purchase_orders (supplier_id, order_date, delivery_date, notes, created_by) VALUES (?, CURDATE(), ?, ?, ?)");
    $stmt->execute([$supplier_id, $delivery_date, $notes, current_user_id()]);
    $orderId = intval($conn->lastInsertId());

    $itemStmt = $conn->prepare("INSERT INTO purchase_order_items (order_id, ingredient_name, quantity, unit) VALUES (?, ?, ?, ?)");
    foreach ($items as $item) {
        $name = isset($item['ingredient_name']) ? trim($item['ingredient_name']) : '';
        $qty = isset($item['quantity']) ? floatval($item['quantity']) : 0;
        $unit = isset($item['unit']) ? trim($item['unit']) : 'kg';
        if ($name === '' || $qty <= 0) {
            continue;
        }
        $itemStmt->execute([$orderId, $name, $qty, $unit]);
    }

    $conn->commit();
    echo json_encode(["status" => "success", "message" => "Order submitted successfully", "order_id" => $orderId]);
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>