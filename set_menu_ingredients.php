<?php
require_once 'config.php';
require_once 'auth.php';

require_role(['kitchen_staff', 'admin']);

$input = json_decode(file_get_contents('php://input'), true);
$menuDate = $input['menu_date'] ?? '';
$mealType = $input['meal_type'] ?? 'lunch';
$items = $input['items'] ?? [];

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $menuDate)) {
    echo json_encode(['status' => 'error', 'message' => 'Ngày thực đơn không hợp lệ']);
    exit;
}

$mealType = in_array($mealType, ['lunch', 'dinner'], true) ? $mealType : 'lunch';

if (!is_array($items)) {
    echo json_encode(['status' => 'error', 'message' => 'Danh sách nguyên liệu không hợp lệ']);
    exit;
}

try {
    $conn->exec("CREATE TABLE IF NOT EXISTS ingredient_recipes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        menu_date DATE NOT NULL,
        meal_type ENUM('lunch','dinner') NOT NULL,
        ingredient_id INT NOT NULL,
        quantity_needed DECIMAL(12,4) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_recipe (menu_date, meal_type, ingredient_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->beginTransaction();

    $delStmt = $conn->prepare('DELETE FROM ingredient_recipes WHERE menu_date = ? AND meal_type = ?');
    $delStmt->execute([$menuDate, $mealType]);

    $insStmt = $conn->prepare('INSERT INTO ingredient_recipes (menu_date, meal_type, ingredient_id, quantity_needed) VALUES (?, ?, ?, ?)');

    $inserted = 0;
    foreach ($items as $item) {
        $ingId = intval($item['ingredient_id'] ?? 0);
        $qty = floatval($item['quantity_needed'] ?? 0);
        if ($ingId <= 0 || $qty <= 0) {
            continue;
        }
        $insStmt->execute([$menuDate, $mealType, $ingId, $qty]);
        $inserted++;
    }

    $conn->commit();
    echo json_encode(['status' => 'success', 'message' => 'Đã lưu nguyên liệu cho thực đơn', 'count' => $inserted]);
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['status' => 'error', 'message' => 'Lỗi: ' . $e->getMessage()]);
}
?>
