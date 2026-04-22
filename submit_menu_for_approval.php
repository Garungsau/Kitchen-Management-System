<?php
/**
 * API: Submit Menu for Approval
 * Purpose: Kitchen submits newly created/updated menu for admin approval
 * Handles: Status change from draft to pending_approval, timestamp recording
 */

require_once 'config.php';
require_once 'auth.php';

// Kitchen drafts menu and submits to admin for approval
require_role(['kitchen_staff', 'admin']);

$input = json_decode(file_get_contents('php://input'), true);
$menu_id = isset($input['menu_id']) ? intval($input['menu_id']) : 0;

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

    // Only allow submission if in draft or rejected status
    if (!in_array($menu['approval_status'], ['draft', 'rejected'])) {
        throw new Exception("Menu must be in draft or rejected status to submit for approval.");
    }

    // Update status to pending_approval
    $stmt = $conn->prepare("UPDATE daily_menu 
                           SET approval_status = 'pending_approval', submitted_by = ?, submitted_at = NOW()
                           WHERE id = ?");
    $stmt->execute([current_user_id(), $menu_id]);

    // Log submission action
    $stmt = $conn->prepare("INSERT INTO menu_approval_logs 
                           (menu_id, menu_date, action, action_by, comments) 
                           VALUES (?, ?, 'submitted', ?, ?)");
    $stmt->execute([
        $menu_id,
        $menu['menu_date'],
        current_user_id(),
        'Menu submitted for approval'
    ]);

    $conn->commit();

    echo json_encode([
        "status" => "success",
        "message" => "Menu submitted for admin approval",
        "menu_id" => $menu_id,
        "menu_date" => $menu['menu_date'],
        "next_step" => "Admin will review and approve/reject"
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
