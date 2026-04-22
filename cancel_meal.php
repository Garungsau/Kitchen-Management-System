<?php
/**
 * API: Cancel Meal Registration
 * Purpose: Allow employee to cancel registered meal before deadline
 * Handles: Refund to wallet, status update, audit logging
 */

require_once 'config.php';
require_once 'auth.php';
start_session_if_needed();

require_role(['employee']);

$input = json_decode(file_get_contents('php://input'), true);
$meal_date = isset($input['meal_date']) ? trim($input['meal_date']) : '';
$reason = isset($input['reason']) ? trim($input['reason']) : 'User cancelled';
$user_id = $_SESSION['user_id'];
$MEAL_COST = MEAL_COST;

if (empty($meal_date)) {
    echo json_encode(["status" => "error", "message" => "Meal date is required."]);
    exit();
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $meal_date)) {
    echo json_encode(["status" => "error", "message" => "Ngày không hợp lệ."]);
    exit();
}

$current_datetime = new DateTime();
$meal_datetime = DateTime::createFromFormat('Y-m-d', $meal_date);
$today_datetime = new DateTime('today');

if (!$meal_datetime) {
    echo json_encode(["status" => "error", "message" => "Ngày không hợp lệ."]);
    exit();
}

if ($meal_datetime <= $today_datetime) {
    echo json_encode(["status" => "error", "message" => "Cannot cancel meal for past dates."]);
    exit();
}

// Check deadline (aligned with toggle_meal)
try {
    $days_ahead = (int)$today_datetime->diff($meal_datetime)->days;

    // Fixed cutoff at 08:00
    $cutoff_time = '08:00';

    $cutoff_datetime = new DateTime($meal_date . ' ' . $cutoff_time . ':00');
    if ($current_datetime >= $cutoff_datetime) {
        echo json_encode([
            "status" => "error",
            "message" => "Cancellation deadline has passed. Deadline was " . $cutoff_time
        ]);
        exit();
    }

    // Begin transaction
    $conn->beginTransaction();

    // Check meal registration exists and is registered
    $stmt = $conn->prepare("SELECT id, status FROM meal_attendance WHERE user_id = ? AND meal_date = ? FOR UPDATE");
    $stmt->execute([$user_id, $meal_date]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$existing || $existing['status'] !== 'registered') {
        throw new Exception("No active registration found for this date.");
    }

    // NOTE: Wallet refund removed - meal costs calculated at month-end for payroll

    // Update meal status
    $stmt = $conn->prepare("UPDATE meal_attendance 
                           SET status = 'cancelled', is_active = 0, cancel_time = NOW() 
                           WHERE user_id = ? AND meal_date = ?");
    $stmt->execute([$user_id, $meal_date]);

    // Log in history
    $stmt = $conn->prepare("INSERT INTO meal_registration_history 
                           (user_id, meal_date, action, new_status, reason) 
                           VALUES (?, ?, 'cancelled', 'cancelled', ?)");
    $stmt->execute([$user_id, $meal_date, $reason]);

    $conn->commit();

    echo json_encode([
        "status" => "success",
        "message" => "Meal cancelled successfully",
        "meal_date" => $meal_date
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
