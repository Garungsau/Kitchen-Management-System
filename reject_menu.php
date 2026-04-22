<?php
/**
 * API: Reject Menu
 * Purpose: Admin rejects a menu submission and requests revision
 * Handles: Status update, rejection reason logging, audit trail
 */

require_once 'config.php';
require_once 'auth.php';

// Only admin can reject menus
require_role(['admin']);

$input = json_decode(file_get_contents('php://input'), true);
$menu_id = isset($input['menu_id']) ? intval($input['menu_id']) : 0;
$reason = isset($input['reason']) ? trim($input['reason']) : '';

if ($menu_id <= 0) {
    echo json_encode(["status" => "error", "message" => "Menu ID is required."]);
    exit();
}

if (empty($reason)) {
    echo json_encode(["status" => "error", "message" => "Rejection reason is required."]);
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
        throw new Exception("Cannot reject an already approved menu.");
    }

    // Update menu status to rejected
    $stmt = $conn->prepare("UPDATE daily_menu 
                           SET approval_status = 'rejected', rejection_reason = ?
                           WHERE id = ?");
    $stmt->execute([$reason, $menu_id]);

    // Log rejection action
    $stmt = $conn->prepare("INSERT INTO menu_approval_logs 
                           (menu_id, menu_date, action, action_by, comments) 
                           VALUES (?, ?, 'rejected', ?, ?)");
    $stmt->execute([
        $menu_id,
        $menu['menu_date'],
        current_user_id(),
        $reason
    ]);

    $conn->commit();

    echo json_encode([
        "status" => "success",
        "message" => "Menu rejected. Admin has been notified to revise.",
        "menu_id" => $menu_id,
        "menu_date" => $menu['menu_date'],
        "rejection_reason" => $reason
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
