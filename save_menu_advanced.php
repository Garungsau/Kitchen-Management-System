<?php
/**
 * Enhanced Menu Save with Nutrition & Image
 * Saves meal with calories, protein, image, etc.
 */
require_once 'config.php';
require_once 'auth.php';

require_role(['kitchen_staff', 'admin']);

$input = json_decode(file_get_contents('php://input'), true);
$date = $input['date'] ?? date('Y-m-d');
$lunch = $input['lunch'] ?? '';
$dinner = $input['dinner'] ?? '';

// Nutrition data (optional)
$lunchNutrition = $input['lunch_nutrition'] ?? ['calories' => 0, 'protein_g' => 0, 'carbs_g' => 0, 'fat_g' => 0];
$dinnerNutrition = $input['dinner_nutrition'] ?? ['calories' => 0, 'protein_g' => 0, 'carbs_g' => 0, 'fat_g' => 0];

// Image URLs (from separate upload endpoint)
$lunchImageUrl = $input['lunch_image_url'] ?? '';
$dinnerImageUrl = $input['dinner_image_url'] ?? '';

try {
    if (!$lunch && !$dinner) {
        echo json_encode(["status" => "error", "message" => "Vui lòng nhập ít nhất một bữa ăn"]);
        exit();
    }

    $sql = "
        INSERT INTO daily_menu (
            menu_date, 
            lunch, dinner, 
            lunch_image_url, lunch_calories, lunch_protein_g, lunch_carbs_g, lunch_fat_g,
            dinner_image_url, dinner_calories, dinner_protein_g, dinner_carbs_g, dinner_fat_g,
            approval_status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft')
        ON DUPLICATE KEY UPDATE 
            lunch = VALUES(lunch), 
            dinner = VALUES(dinner),
            lunch_image_url = VALUES(lunch_image_url),
            lunch_calories = VALUES(lunch_calories),
            lunch_protein_g = VALUES(lunch_protein_g),
            lunch_carbs_g = VALUES(lunch_carbs_g),
            lunch_fat_g = VALUES(lunch_fat_g),
            dinner_image_url = VALUES(dinner_image_url),
            dinner_calories = VALUES(dinner_calories),
            dinner_protein_g = VALUES(dinner_protein_g),
            dinner_carbs_g = VALUES(dinner_carbs_g),
            dinner_fat_g = VALUES(dinner_fat_g),
            approval_status = 'draft',
            approved_by = NULL,
            approved_at = NULL,
            rejection_reason = NULL
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        $date,
        $lunch, $dinner,
        $lunchImageUrl,
        intval($lunchNutrition['calories'] ?? 0),
        floatval($lunchNutrition['protein_g'] ?? 0),
        floatval($lunchNutrition['carbs_g'] ?? 0),
        floatval($lunchNutrition['fat_g'] ?? 0),
        $dinnerImageUrl,
        intval($dinnerNutrition['calories'] ?? 0),
        floatval($dinnerNutrition['protein_g'] ?? 0),
        floatval($dinnerNutrition['carbs_g'] ?? 0),
        floatval($dinnerNutrition['fat_g'] ?? 0)
    ]);

    $idStmt = $conn->prepare("SELECT id FROM daily_menu WHERE menu_date = ? LIMIT 1");
    $idStmt->execute([$date]);
    $menuId = $idStmt->fetchColumn();

    // Track in history if approved
    if ($input['save_to_history'] ?? false) {
        $histStmt = $conn->prepare("
            INSERT INTO menu_history (menu_date, shift, meal_name, served_by)
            VALUES (?, ?, ?, ?)
        ");
        if ($lunch) $histStmt->execute([$date, 'lunch', $lunch, $_SESSION['user_id'] ?? null]);
        if ($dinner) $histStmt->execute([$date, 'dinner', $dinner, $_SESSION['user_id'] ?? null]);
    }

    echo json_encode([
        "status" => "success",
        "message" => "Lưu thực đơn thành công",
        "menu_id" => intval($menuId)
    ]);

} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "Lỗi DB: " . $e->getMessage()]);
}
?>
