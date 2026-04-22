<?php
require_once 'config.php';
require_once 'auth.php';
start_session_if_needed();
require_role(['kitchen_staff', 'admin']);

function column_exists(PDO $conn, string $table, string $column): bool {
    try {
        $stmt = $conn->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        $stmt->execute([$column]);
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return false;
    }
}

$input = json_decode(file_get_contents('php://input'), true);
$employee_id = isset($input['employee_id']) ? trim($input['employee_id']) : '';
$user_id = isset($input['user_id']) ? intval($input['user_id']) : 0;
$meal_date = isset($input['meal_date']) ? trim($input['meal_date']) : date('Y-m-d');
$meal_type = isset($input['meal_type']) ? trim($input['meal_type']) : 'lunch';
$check_in_type = isset($input['check_in_type']) ? trim($input['check_in_type']) : 'id_card'; // id_card or face_recognition
$RATE_LIMIT_SECONDS = 5;

if ($employee_id === '' && $user_id === 0) {
    echo json_encode(["status" => "error", "message" => "Employee ID or User ID is required"]);
    exit();
}

if (!in_array($meal_type, ['lunch', 'dinner'], true)) {
    echo json_encode(["status" => "error", "message" => "Loại suất ăn không hợp lệ."]);
    exit();
}

try {
    $hasStatusColumn = column_exists($conn, 'meal_attendance', 'status');
    $hasCheckInTimeColumn = column_exists($conn, 'meal_attendance', 'check_in_time');

    $today = date('Y-m-d');
    if ($meal_date !== $today) {
        echo json_encode(["status" => "error", "message" => "Check-in can only be recorded for today."]);
        exit();
    }

    // Get employee by ID or user_id
    if ($user_id > 0) {
        // Face recognition check-in: use user_id directly
        $stmt = $conn->prepare("SELECT u.id as user_id, s.full_name, s.student_id_no, s.department
                                FROM users u
                                LEFT JOIN students s ON u.id = s.user_id
                                WHERE u.id = ?");
        $stmt->execute([$user_id]);
    } else {
        // Traditional check-in: lookup by employee_id
        $stmt = $conn->prepare("SELECT u.id as user_id, s.full_name, s.student_id_no, s.department
                                FROM users u
                                LEFT JOIN students s ON u.id = s.user_id
                                WHERE s.student_id_no = ?");
        $stmt->execute([$employee_id]);
    }
    
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$employee && $user_id === 0 && ctype_digit($employee_id)) {
        $stmt = $conn->prepare("SELECT u.id as user_id, s.full_name, s.student_id_no, s.department
                                FROM users u
                                LEFT JOIN students s ON u.id = s.user_id
                                WHERE u.id = ?");
        $stmt->execute([intval($employee_id)]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$employee && $user_id === 0 && strpos($employee_id, '@') !== false) {
        $stmt = $conn->prepare("SELECT u.id as user_id, s.full_name, s.student_id_no, s.department
                                FROM users u
                                LEFT JOIN students s ON u.id = s.user_id
                                WHERE u.email = ?");
        $stmt->execute([$employee_id]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$employee) {
        echo json_encode(["status" => "error", "message" => "Employee not found."]);
        exit();
    }

    $target_user_id = intval($employee['user_id']);
    $check_in_time = date('H:i:s');

    $conn->beginTransaction();

    $selectFields = ['id', 'is_active'];
    if ($hasStatusColumn) $selectFields[] = 'status';
    if ($hasCheckInTimeColumn) $selectFields[] = 'check_in_time';

    $stmt = $conn->prepare("SELECT " . implode(', ', $selectFields) . "
                            FROM meal_attendance
                            WHERE user_id = ? AND meal_date = ?
                            LIMIT 1 FOR UPDATE");
    $stmt->execute([$target_user_id, $meal_date]);
    $meal = $stmt->fetch(PDO::FETCH_ASSOC);

    $nowTs = time();
    $lastCheckTs = ($hasCheckInTimeColumn && $meal && !empty($meal['check_in_time'])) ? strtotime($meal['check_in_time']) : 0;
    $timeSinceLastCheck = $lastCheckTs ? ($nowTs - $lastCheckTs) : PHP_INT_MAX;

    $alreadyCheckedIn = false;
    if ($meal) {
        if ($hasStatusColumn && ($meal['status'] ?? '') === 'checked_in') {
            $alreadyCheckedIn = true;
        } elseif (!$hasStatusColumn && $hasCheckInTimeColumn && !empty($meal['check_in_time'])) {
            $alreadyCheckedIn = true;
        }
    }

    if ($alreadyCheckedIn) {
        if ($timeSinceLastCheck < $RATE_LIMIT_SECONDS) {
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
            "employee_name" => $employee['full_name'],
            "employee_id" => $employee['student_id_no'] ?: $employee_id,
            "department" => $employee['department'] ?? '-',
            "meal_date" => $meal_date,
            "meal_type" => $meal_type,
            "check_in_time" => $hasCheckInTimeColumn ? ($meal['check_in_time'] ?? $check_in_time) : $check_in_time,
            "check_in_type" => $check_in_type,
            "is_registered_meal" => 1,
            "registration_status" => $hasStatusColumn ? ($meal['status'] ?? 'checked_in') : 'checked_in',
            "message" => "Nhân viên đã được check-in trước đó."
        ]);
        exit();
    }

    if ($timeSinceLastCheck < $RATE_LIMIT_SECONDS) {
        $conn->rollBack();
        echo json_encode([
            "status" => "error",
            "message" => "Bạn thao tác quá nhanh, vui lòng thử lại sau vài giây."
        ]);
        exit();
    }

    $registered = false;
    if ($meal) {
        if ($hasStatusColumn) {
            $registered = in_array(($meal['status'] ?? ''), ['registered', 'checked_in'], true);
        } else {
            $registered = intval($meal['is_active'] ?? 0) === 1;
        }
    }

    if (!$registered) {
        $conn->rollBack();
        echo json_encode([
            "status" => "error",
            "message" => "Chưa đăng ký suất ăn cho hôm nay. Vui lòng đăng ký trước khi check-in.",
            "employee_name" => $employee['full_name'],
            "employee_id" => $employee['student_id_no'] ?: $employee_id,
            "department" => $employee['department'] ?? '-',
            "meal_date" => $meal_date,
            "meal_type" => $meal_type,
            "registration_status" => $hasStatusColumn ? ($meal['status'] ?? 'not_registered') : ($registered ? 'registered' : 'not_registered')
        ]);
        exit();
    }

    $updateParts = ["is_active = 1"];
    $updateParams = [];
    if ($hasStatusColumn) {
        $updateParts[] = "status = 'checked_in'";
    }
    if ($hasCheckInTimeColumn) {
        $updateParts[] = "check_in_time = ?";
        $updateParams[] = $check_in_time;
    }
    $updateParams[] = $meal['id'];

    $stmt = $conn->prepare("UPDATE meal_attendance
                            SET " . implode(', ', $updateParts) . "
                            WHERE id = ?");
    $stmt->execute($updateParams);

    // NOTE: Subsidy refund logic removed - system no longer manages wallet
    // Meal costs calculated at month-end by HR department for payroll

    try {
        $stmt = $conn->prepare("INSERT INTO meal_registration_history
                                (user_id, meal_date, action, previous_status, new_status, reason)
                                VALUES (?, ?, 'edited', ?, ?, ?)");
        $stmt->execute([
            $target_user_id,
            $meal_date,
            $hasStatusColumn ? ($meal['status'] ?? 'none') : (intval($meal['is_active'] ?? 0) === 1 ? 'registered' : 'none'),
            'checked_in',
            'Check-in via ' . ($check_in_type === 'face_recognition' ? 'face recognition' : 'ID card') . ' at ' . $check_in_time
        ]);
    } catch (Exception $ignore) {
        // Ignore if history table is unavailable.
    }

    $conn->commit();

    echo json_encode([
        "status" => "success",
        "employee_name" => $employee['full_name'],
        "employee_id" => $employee['student_id_no'] ?: $employee_id,
        "department" => $employee['department'] ?? '-',
        "meal_date" => $meal_date,
        "meal_type" => $meal_type,
        "check_in_time" => $check_in_time,
        "check_in_type" => $check_in_type,
        "is_registered_meal" => 1,
        "registration_status" => $hasStatusColumn ? ($meal['status'] ?? 'registered') : 'registered',
        "message" => "Check-in thành công cho suất đã đăng ký."
    ]);
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>