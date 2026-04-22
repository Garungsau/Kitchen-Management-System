<?php
require_once 'config.php';
require_once 'auth.php';

require_role(['admin']);

$input = json_decode(file_get_contents('php://input'), true);
$student_id_no = $input['student_id'];
$action = strtoupper($input['action']);
$date = date('Y-m-d', strtotime('+1 day'));

try {
    $conn->beginTransaction();

    // Get user_id from student_id_no
    $stmt = $conn->prepare("SELECT user_id FROM students WHERE student_id_no = ? FOR UPDATE");
    $stmt->execute([$student_id_no]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        throw new Exception("Student ID not found");
    }

    $uid = $student['user_id'];

    // Check current meal registration status
    $stmt = $conn->prepare("SELECT is_active FROM meal_attendance WHERE user_id = ? AND meal_date = ?");
    $stmt->execute([$uid, $date]);
    $status_row = $stmt->fetchColumn();

    $current_status = ($status_row === false) ? 0 : $status_row;
    $target_status = ($action === 'ON') ? 1 : 0;

    if ($current_status == $target_status) {
        $conn->rollBack();
        echo json_encode(["status" => "success", "message" => "Meal is already $action for this student."]);
        exit();
    }

    // NOTE: Wallet operations removed - meal costs calculated at month-end for payroll
    
    if ($action === 'ON') {
        $sql = "INSERT INTO meal_attendance (user_id, meal_date, is_active, status, registration_time) 
                VALUES (?, ?, 1, 'registered', NOW()) 
                ON DUPLICATE KEY UPDATE is_active = 1, status = 'registered'";
        $conn->prepare($sql)->execute([$uid, $date]);

    } else {
        $sql = "INSERT INTO meal_attendance (user_id, meal_date, is_active, status, cancel_time) 
                VALUES (?, ?, 0, 'cancelled', NOW()) 
                ON DUPLICATE KEY UPDATE is_active = 0, status = 'cancelled'";
        $conn->prepare($sql)->execute([$uid, $date]);
    }

    $conn->commit();
    echo json_encode(["status" => "success", "message" => "Meal status updated to $action by admin."]);

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>