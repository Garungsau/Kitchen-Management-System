<?php
require_once 'config.php';
require_once 'auth.php';

require_role(['kitchen_staff', 'admin']);

$input = json_decode(file_get_contents('php://input'), true);
$id = isset($input['id']) ? intval($input['id']) : 0;
$name = isset($input['name']) ? trim($input['name']) : '';
$unit = isset($input['unit']) ? trim($input['unit']) : 'kg';
$stock = isset($input['stock_qty']) ? floatval($input['stock_qty']) : 0;
$norm = isset($input['per_meal_norm']) ? floatval($input['per_meal_norm']) : 0;
$threshold = isset($input['low_stock_threshold']) ? floatval($input['low_stock_threshold']) : 0;

if ($name === '') {
    echo json_encode(["status" => "error", "message" => "Ingredient name is required"]);
    exit();
}

try {
    $conn->exec("CREATE TABLE IF NOT EXISTS ingredients (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(120) NOT NULL,
        unit VARCHAR(20) NOT NULL DEFAULT 'kg',
        stock_qty DECIMAL(12,2) NOT NULL DEFAULT 0,
        per_meal_norm DECIMAL(10,4) NOT NULL DEFAULT 0,
        low_stock_threshold DECIMAL(12,2) NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_name (name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE ingredients SET name = ?, unit = ?, stock_qty = ?, per_meal_norm = ?, low_stock_threshold = ? WHERE id = ?");
        $stmt->execute([$name, $unit, $stock, $norm, $threshold, $id]);
    } else {
        $stmt = $conn->prepare("INSERT INTO ingredients (name, unit, stock_qty, per_meal_norm, low_stock_threshold) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $unit, $stock, $norm, $threshold]);
    }

    echo json_encode(["status" => "success", "message" => "Ingredient saved successfully"]);
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>