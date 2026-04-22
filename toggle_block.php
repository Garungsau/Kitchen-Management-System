<?php
require_once 'config.php';
require_once 'auth.php';

require_role(['admin']);

$input = json_decode(file_get_contents('php://input'), true);
$target_id = isset($input['id']) ? intval($input['id']) : 0;
$block_status = isset($input['block_status']) ? intval($input['block_status']) : 0; 

if ($target_id <= 0) {
    echo json_encode(["status" => "error", "message" => "Invalid ID"]);
    exit();
}

if ($target_id === current_user_id()) {
    echo json_encode(["status" => "error", "message" => "You cannot block yourself!"]);
    exit();
}

try {
    $stmt = $conn->prepare("UPDATE users SET is_blocked = ? WHERE id = ?");
    $stmt->execute([$block_status, $target_id]);

    $action = ($block_status == 1) ? "Blocked" : "Unblocked";
    echo json_encode(["status" => "success", "message" => "User $action successfully."]);

} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "Database error"]);
}
?>