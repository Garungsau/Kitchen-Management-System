<?php
require_once 'config.php';
require_once 'auth.php';

require_role(['kitchen_staff', 'admin']);

try {
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

    // Alias fields for compatibility and add stock status
    $mealDate = isset($_GET['meal_date']) ? trim($_GET['meal_date']) : date('Y-m-d', strtotime('+1 day'));
    $stmtMeal = $conn->prepare("SELECT COUNT(*) FROM meal_attendance WHERE meal_date = ? AND status IN ('registered','checked_in')");
    $stmtMeal->execute([$mealDate]);
    $mealCount = intval($stmtMeal->fetchColumn());

    $stmt = $conn->query("SELECT 
        id, 
        name, 
        unit, 
        stock_qty as quantity_in_stock, 
        per_meal_norm, 
        low_stock_threshold as min_threshold, 
        last_unit_price,
        is_active,
        CASE 
            WHEN stock_qty <= low_stock_threshold THEN 'low'
            WHEN stock_qty <= (low_stock_threshold * 1.5) THEN 'warning'
            ELSE 'ok'
        END as stock_status
    FROM ingredients WHERE is_active = 1 ORDER BY stock_qty ASC, name ASC");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // For legacy compatibility, also add quantity, unit, type, price, date fields
    foreach ($rows as &$r) {
        $r['quantity'] = $r['quantity_in_stock'];
        $r['type'] = 'ingredient';
        $r['price'] = floatval($r['last_unit_price'] ?? 0);
        $r['date'] = date('Y-m-d');
        
        $need = round(floatval($r['per_meal_norm']) * $mealCount, 2);
        $r['need_today'] = $need;
        $r['shortage'] = max(0, $need - floatval($r['quantity_in_stock']));
        $r['is_low_stock'] = (floatval($r['quantity_in_stock']) <= floatval($r['min_threshold'])) ? 1 : 0;
    }

    echo json_encode(["status" => "success", "meal_count" => $mealCount, "data" => $rows]);
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>