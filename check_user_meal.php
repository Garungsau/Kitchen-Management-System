<?php
require_once 'config.php';
require_once 'auth.php';

require_role(['kitchen_staff', 'admin']);

$input = json_decode(file_get_contents('php://input'), true);
$employee_id = isset($input['employee_id']) ? trim($input['employee_id']) : '';
if ($employee_id === '') {
    $employee_id = isset($input['student_id']) ? trim($input['student_id']) : '';
}
$date = isset($input['date']) ? $input['date'] : date('Y-m-d');

if(empty($employee_id)) {
    echo json_encode(["status" => "error", "message" => "Enter Employee ID"]);
    exit();
}

try {
    $stmt = $conn->prepare("SELECT user_id, full_name, photo_path, hall_name, department, student_id_no FROM students WHERE student_id_no = ?");
    $stmt->execute([$employee_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if(!$student) {
        echo json_encode(["status" => "error", "message" => "Employee not found"]);
        exit();
    }

    $stmt = $conn->prepare("SELECT is_active, status, check_in_time FROM meal_attendance WHERE user_id = ? AND meal_date = ?");
    $stmt->execute([$student['user_id'], $date]);
    $attendance = $stmt->fetch(PDO::FETCH_ASSOC);

    $is_active = ($attendance === false) ? 0 : intval($attendance['is_active']);
    $registration_status = ($attendance === false) ? 'not_registered' : ($attendance['status'] ?: 'registered');
    $check_in_time = ($attendance === false) ? null : $attendance['check_in_time'];

    echo json_encode([
        "status" => "success",
        "data" => [
            "name" => $student['full_name'],
            "employee_id" => $student['student_id_no'],
            "department" => $student['department'],
            "hall" => $student['hall_name'],
            "photo" => $student['photo_path'],
            "date" => $date,
            "meal_status" => $is_active,
            "registration_status" => $registration_status,
            "check_in_time" => $check_in_time
        ]
    ]);

} catch (PDOException $e) { echo json_encode(["status" => "error"]); }
?>