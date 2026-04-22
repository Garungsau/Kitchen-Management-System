<?php
require_once 'config.php';
require_once 'auth.php';

require_role(['kitchen_staff', 'admin']);

$input = json_decode(file_get_contents('php://input'), true);
$menuDate = $input['menu_date'] ?? '';
$mealType = $input['meal_type'] ?? null;
$force = isset($input['force']) ? (bool)$input['force'] : false;

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $menuDate)) {
    echo json_encode(['status' => 'error', 'message' => 'Ngày thực đơn không hợp lệ']);
    exit;
}

$hasMealType = in_array($mealType, ['lunch', 'dinner'], true);

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

    $conn->exec("CREATE TABLE IF NOT EXISTS inventory_transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ingredient_id INT NOT NULL,
        transaction_type ENUM('in','out','adjustment') NOT NULL,
        quantity DECIMAL(12,2) NOT NULL,
        reference_type VARCHAR(50),
        reference_id INT NULL,
        notes TEXT,
        created_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_transaction_type (transaction_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->exec("CREATE TABLE IF NOT EXISTS deduction_runs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        menu_date DATE NOT NULL,
        meal_type ENUM('lunch','dinner','both') NOT NULL DEFAULT 'both',
        run_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_run (menu_date, meal_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    if (!$force) {
        $chk = $conn->prepare('SELECT id FROM deduction_runs WHERE menu_date = ? AND meal_type = ? LIMIT 1');
        $chk->execute([$menuDate, $hasMealType ? $mealType : 'both']);
        if ($chk->fetch()) {
            echo json_encode(['status' => 'error', 'message' => 'Đã trừ kho cho ngày này. Thêm force=true để chạy lại.']);
            exit;
        }
    }

    $mealStmt = $conn->prepare("SELECT COUNT(*) FROM meal_attendance WHERE meal_date = ? AND status IN ('registered','checked_in')");
    $mealStmt->execute([$menuDate]);
    $mealCount = intval($mealStmt->fetchColumn() ?: 0);

    $guestStmt = $conn->prepare("SELECT COALESCE(SUM(quantity),0) FROM guest_bookings WHERE booking_date = ?");
    $guestStmt->execute([$menuDate]);
    $guestCount = intval($guestStmt->fetchColumn() ?: 0);

    $portions = $mealCount + $guestCount;
    if ($portions <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Chưa có suất ăn đăng ký cho ngày này']);
        exit;
    }

    $sql = "SELECT ir.menu_date, ir.meal_type, ir.ingredient_id, ir.quantity_needed, i.stock_qty, i.unit
            FROM ingredient_recipes ir
            JOIN ingredients i ON i.id = ir.ingredient_id
            WHERE ir.menu_date = ?";
    $params = [$menuDate];
    if ($hasMealType) {
        $sql .= ' AND ir.meal_type = ?';
        $params[] = $mealType;
    }
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $recipes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$recipes || count($recipes) === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Chưa gán nguyên liệu cho thực đơn ngày này']);
        exit;
    }

    $shortages = [];
    $deductList = [];
    foreach ($recipes as $r) {
        $need = round(floatval($r['quantity_needed']) * $portions, 2);
        $stock = floatval($r['stock_qty']);
        if ($stock < $need) {
            $shortages[] = [
                'ingredient_id' => $r['ingredient_id'],
                'meal_type' => $r['meal_type'],
                'needed' => $need,
                'stock' => $stock,
                'shortage' => round($need - $stock, 2)
            ];
        }
        $deductList[] = [
            'ingredient_id' => $r['ingredient_id'],
            'meal_type' => $r['meal_type'],
            'quantity' => $need
        ];
    }

    if (!empty($shortages)) {
        echo json_encode(['status' => 'error', 'message' => 'Không đủ tồn kho để trừ', 'shortages' => $shortages]);
        exit;
    }

    $conn->beginTransaction();

    $upd = $conn->prepare('UPDATE ingredients SET stock_qty = stock_qty - ? WHERE id = ?');
    $log = $conn->prepare('INSERT INTO inventory_transactions (ingredient_id, transaction_type, quantity, reference_type, reference_id, notes, created_by) VALUES (?, "out", ?, "daily_usage", NULL, ?, ?)');

    foreach ($deductList as $d) {
        $upd->execute([$d['quantity'], $d['ingredient_id']]);
        $note = 'Trừ kho cho thực đơn ' . $menuDate . ' (' . $d['meal_type'] . ')';
        $log->execute([$d['ingredient_id'], $d['quantity'], $note, current_user_id()]);
    }

    $runStmt = $conn->prepare('INSERT INTO deduction_runs (menu_date, meal_type) VALUES (?, ?) ON DUPLICATE KEY UPDATE run_at = CURRENT_TIMESTAMP');
    $runStmt->execute([$menuDate, $hasMealType ? $mealType : 'both']);

    $conn->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'Đã trừ kho thành công',
        'portions' => $portions,
        'count' => count($deductList)
    ]);
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['status' => 'error', 'message' => 'Lỗi: ' . $e->getMessage()]);
}
?>
