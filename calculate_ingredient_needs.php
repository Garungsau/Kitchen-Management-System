<?php
// API: Tính nhu cầu nguyên liệu dựa trên thực đơn và suất ăn thực tế
require_once 'config.php';
require_once 'auth.php';

require_role(['kitchen_staff', 'admin']);

try {
    $mealDate = $_GET['date'] ?? date('Y-m-d');
    $mealType = $_GET['meal_type'] ?? null; // lunch, dinner, hoặc null

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $mealDate)) {
        echo json_encode(['status' => 'error', 'message' => 'Ngày không hợp lệ']);
        exit;
    }

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

    // Đảm bảo cột last_unit_price tồn tại để tính giá ước tính
    try {
        $conn->query("SELECT last_unit_price FROM ingredients LIMIT 1");
    } catch (Exception $colEx) {
        try {
            $conn->exec("ALTER TABLE ingredients ADD COLUMN last_unit_price DECIMAL(12,2) NOT NULL DEFAULT 0");
        } catch (Exception $ignore) {}
    }

    $mealStmt = $conn->prepare("SELECT COUNT(*) FROM meal_attendance WHERE meal_date = ? AND status IN ('registered','checked_in')");
    $mealStmt->execute([$mealDate]);
    $mealCount = intval($mealStmt->fetchColumn() ?: 0);

    $guestStmt = $conn->prepare("SELECT COALESCE(SUM(quantity),0) FROM guest_bookings WHERE booking_date = ?");
    $guestStmt->execute([$mealDate]);
    $guestCount = intval($guestStmt->fetchColumn() ?: 0);

    $totalMeals = max(0, $mealCount + $guestCount);

    $sql = "SELECT ir.menu_date, ir.meal_type, ir.ingredient_id, ir.quantity_needed,
                   i.name, i.unit, i.stock_qty, i.low_stock_threshold, i.last_unit_price
            FROM ingredient_recipes ir
            JOIN ingredients i ON i.id = ir.ingredient_id
            WHERE ir.menu_date = ?";
    $params = [$mealDate];
    if (in_array($mealType, ['lunch', 'dinner'], true)) {
        $sql .= ' AND ir.meal_type = ?';
        $params[] = $mealType;
    }
    $sql .= ' ORDER BY ir.meal_type, i.name';

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $results = [];
    $totalCost = 0;

    foreach ($rows as $r) {
        $needed = $totalMeals > 0 ? floatval($r['quantity_needed']) * $totalMeals : 0;
        $stock = floatval($r['stock_qty']);
        $unitPrice = floatval($r['last_unit_price'] ?? 0);
        $cost = $unitPrice * $needed;
        $shortage = max(0, $needed - $stock);

        $results[] = [
            'ingredient' => $r['name'],
            'meal_type' => $r['meal_type'],
            'usage_per_portion' => floatval($r['quantity_needed']),
            'unit' => $r['unit'] ?? 'kg',
            'unit_price' => $unitPrice,
            'total_needed' => round($needed, 2),
            'current_stock' => $stock,
            'shortage' => round($shortage, 2),
            'total_cost' => round($cost, 0),
            'is_low_stock' => ($stock <= floatval($r['low_stock_threshold'] ?? 0))
        ];

        $totalCost += $cost;
    }

    echo json_encode([
        'status' => 'success',
        'meal_date' => $mealDate,
        'meal_type' => $mealType,
        'meal_count' => $mealCount,
        'guest_count' => $guestCount,
        'total_meals' => $totalMeals,
        'data' => $results,
        'total_estimated_cost' => round($totalCost, 0)
    ]);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Lỗi: ' . $e->getMessage()]);
}
?>
