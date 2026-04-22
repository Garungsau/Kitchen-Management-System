<?php
require_once 'config.php';

$data = json_decode(file_get_contents('php://input'), true);
$name = trim($data['name'] ?? '');
$type = $data['type'] ?? '';
$unit = trim($data['unit'] ?? '');
$quantity = floatval($data['quantity'] ?? 0);
$price = floatval($data['price'] ?? 0);
$date = $data['date'] ?? date('Y-m-d');

if (!$name || !$type || !$unit) {
    echo json_encode(['status' => 'error', 'message' => 'Vui lòng điền đầy đủ thông tin']);
    exit;
}

try {
    // Bảng cũ để giữ tương thích lịch sử
    $stmt = $conn->prepare("INSERT INTO kitchen_ingredients (name, type, unit, quantity, unit_price, date_added) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$name, $type, $unit, $quantity, $price, $date]);

    // Bảng mới để quản lý tồn kho hiện tại
    $conn->exec("CREATE TABLE IF NOT EXISTS ingredients (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(120) NOT NULL,
        unit VARCHAR(20) NOT NULL DEFAULT 'kg',
        stock_qty DECIMAL(12,2) NOT NULL DEFAULT 0,
        per_meal_norm DECIMAL(10,4) NOT NULL DEFAULT 0,
        low_stock_threshold DECIMAL(12,2) NOT NULL DEFAULT 0,
        last_unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_name (name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $stmt2 = $conn->prepare("INSERT INTO ingredients (name, unit, stock_qty, last_unit_price) VALUES (?, ?, ?, ?) 
        ON DUPLICATE KEY UPDATE stock_qty = stock_qty + VALUES(stock_qty), last_unit_price = VALUES(last_unit_price)");
    $stmt2->execute([$name, $unit, $quantity, $price]);

    echo json_encode(['status' => 'success', 'message' => 'Thêm thành công']);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Lỗi: ' . $e->getMessage()]);
}
?>
