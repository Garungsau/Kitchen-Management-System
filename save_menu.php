<?php
require_once 'config.php';
require_once 'auth.php';

require_role(['kitchen_staff', 'admin']);

$input = json_decode(file_get_contents('php://input'), true);
$date = $input['date'];
$lunch = $input['lunch'];
$dinner = $input['dinner'];

try {
        $sql = "INSERT INTO daily_menu (menu_date, lunch, dinner, approval_status) 
            VALUES (?, ?, ?, 'draft')
            ON DUPLICATE KEY UPDATE 
            lunch = VALUES(lunch), 
            dinner = VALUES(dinner),
            approval_status = 'draft',
            approved_by = NULL,
            approved_at = NULL,
            rejection_reason = NULL";
            
    $stmt = $conn->prepare($sql);
        $stmt->execute([$date, $lunch, $dinner]);

    $idStmt = $conn->prepare("SELECT id FROM daily_menu WHERE menu_date = ? LIMIT 1");
    $idStmt->execute([$date]);
    $menuId = $idStmt->fetchColumn();

    echo json_encode([
        "status" => "success",
        "message" => "Menu draft saved successfully.",
        "menu_id" => intval($menuId)
    ]);

} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "Database Error: " . $e->getMessage()]);
}
?>