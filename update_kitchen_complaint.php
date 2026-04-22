<?php
require_once 'config.php';
require_once 'auth.php';

require_role(['kitchen_staff', 'admin']);

$input = json_decode(file_get_contents('php://input'), true);
$id = isset($input['id']) ? intval($input['id']) : 0;
$new_status = isset($input['status']) ? trim($input['status']) : '';
$note = isset($input['note']) ? trim($input['note']) : '';

$allowed = ['in_progress', 'resolved', 'rejected'];
if ($id <= 0 || !in_array($new_status, $allowed, true)) {
    echo json_encode(["status" => "error", "message" => "Invalid input"]);
    exit();
}

try {
    $stmt = $conn->prepare("UPDATE meal_complaints SET status = ?, admin_note = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$new_status, $note, $id]);

    if ($stmt->rowCount() === 0) {
        echo json_encode(["status" => "error", "message" => "Complaint not found or unchanged"]);
        exit();
    }

    echo json_encode(["status" => "success", "message" => "Complaint updated successfully"]);
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>