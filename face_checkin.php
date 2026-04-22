<?php
require_once 'config.php';
require_once 'auth.php';
start_session_if_needed();
require_role(['kitchen_staff', 'admin']);

$input = json_decode(file_get_contents('php://input'), true);
$employee_id = isset($input['employee_id']) ? trim($input['employee_id']) : '';
$meal_date = isset($input['meal_date']) ? trim($input['meal_date']) : date('Y-m-d');
$shift = isset($input['shift']) ? trim($input['shift']) : 'lunch';
$is_manual = !empty($input['is_manual']) ? 1 : 0;
$RATE_LIMIT_SECONDS = 5;

if ($employee_id === '') {
    echo json_encode(["status" => "error", "message" => "Employee ID is required"]);
    exit();
}

try {
    $today = date('Y-m-d');
    if ($meal_date !== $today) {
        echo json_encode(["status" => "error", "message" => "Chỉ hỗ trợ check-in cho ngày hiện tại."]);
        exit();
    }

    $stmt = $conn->prepare("SELECT user_id, full_name, student_id_no, department
                            FROM students
                            WHERE student_id_no = ?");
    $stmt->execute([$employee_id]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$employee) {
        echo json_encode(["status" => "error", "message" => "Không tìm thấy nhân viên."]);
        exit();
    }

    $target_user_id = intval($employee['user_id']);
    $check_in_time = date('H:i:s');

    $conn->beginTransaction();

    $stmt = $conn->prepare("SELECT id, status, is_active, check_in_time
                            FROM meal_attendance
                            WHERE user_id = ? AND meal_date = ?
                            LIMIT 1 FOR UPDATE");
    $stmt->execute([$target_user_id, $meal_date]);
    $meal = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($meal && $meal['status'] === 'checked_in') {
        $nowTs = time();
        $lastCheckTs = !empty($meal['check_in_time']) ? strtotime($meal['check_in_time']) : 0;
        if ($lastCheckTs && ($nowTs - $lastCheckTs) < $RATE_LIMIT_SECONDS) {
            $conn->rollBack();
            echo json_encode([
                "status" => "error",
                "message" => "Bạn thao tác quá nhanh, vui lòng thử lại sau vài giây."
            ]);
            exit();
        }

        $conn->commit();
        echo json_encode([
            "status" => "already_checkedin",
            "name" => $employee['full_name'],
            "employee_id" => $employee['student_id_no'],
            "department" => $employee['department'] ?? '-',
            "message" => "Nhân viên đã check-in trước đó."
        ]);
        exit();
    }

    $is_registered = $meal && in_array($meal['status'], ['registered']);

    if ($meal) {
        // Always mark walk-in and registered as active when checked in
        $stmt = $conn->prepare("UPDATE meal_attendance
                                SET status = 'checked_in', check_in_time = ?, is_active = 1
                                WHERE id = ?");
        $stmt->execute([$check_in_time, $meal['id']]);
    } else {
        // True walk-in: create active checked-in record (counts toward on_count)
        $stmt = $conn->prepare("INSERT INTO meal_attendance
                                (user_id, meal_date, is_active, status, registration_time, check_in_time)
                                VALUES (?, ?, 1, 'checked_in', NULL, ?)");
        $stmt->execute([$target_user_id, $meal_date, $check_in_time]);
    }

    try {
        $stmt = $conn->prepare("INSERT INTO meal_registration_history
                                (user_id, meal_date, action, previous_status, new_status, reason, meal_type)
                                VALUES (?, ?, 'edited', ?, ?, ?, ?)");
        $stmt->execute([
            $target_user_id,
            $meal_date,
            $meal['status'] ?? 'none',
            $is_registered ? 'checked_in' : 'walk_in',
            ($is_manual ? 'Manual' : 'Face') . ' check-in at ' . $check_in_time,
            $shift
        ]);
    } catch (Exception $ignore) {
        // Ignore if history table is unavailable.
    }

    $conn->commit();

    echo json_encode([
        "status" => "success",
        "name" => $employee['full_name'],
        "employee_id" => $employee['student_id_no'],
        "department" => $employee['department'] ?? '-',
        "check_in_time" => $check_in_time,
        "shift" => $shift,
        "is_registered" => $is_registered ? 1 : 0,
        "message" => $is_registered
            ? "Check-in thành công cho suất đã đăng ký."
            : "Đã ghi nhận suất phát sinh."
    ]);
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>