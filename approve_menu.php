<?php
/**
 * API: Approve Menu
 * Purpose: Admin approves a menu for display to employees
 * Handles: Status update, timestamp recording, approval logging
 */

require_once 'config.php';
require_once 'auth.php';

// Only admin can approve menus
require_role(['admin']);

$input = json_decode(file_get_contents('php://input'), true);
$menu_id = isset($input['menu_id']) ? intval($input['menu_id']) : 0;
$comments = isset($input['comments']) ? trim($input['comments']) : '';

if ($menu_id <= 0) {
    echo json_encode(["status" => "error", "message" => "Menu ID is required."]);
    exit();
}

try {
    $conn->beginTransaction();

    // Get menu details
    $stmt = $conn->prepare("SELECT id, menu_date, approval_status FROM daily_menu WHERE id = ?");
    $stmt->execute([$menu_id]);
    $menu = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$menu) {
        throw new Exception("Menu not found.");
    }

    if ($menu['approval_status'] === 'approved') {
        throw new Exception("Menu is already approved.");
    }

    // Update menu status
    $stmt = $conn->prepare("UPDATE daily_menu 
                           SET approval_status = 'approved', approved_by = ?, approved_at = NOW()
                           WHERE id = ?");
    $stmt->execute([current_user_id(), $menu_id]);

    // Log approval action
    $stmt = $conn->prepare("INSERT INTO menu_approval_logs 
                           (menu_id, menu_date, action, action_by, comments) 
                           VALUES (?, ?, 'approved', ?, ?)");
    $stmt->execute([
        $menu_id,
        $menu['menu_date'],
        current_user_id(),
        $comments
    ]);

    $conn->commit();

    echo json_encode([
        "status" => "success",
        "message" => "Menu approved successfully for " . $menu['menu_date'],
        "menu_id" => $menu_id,
        "menu_date" => $menu['menu_date']
    ]);

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}
?>
