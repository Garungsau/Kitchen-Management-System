<?php
require_once 'config.php';
require_once 'auth.php';

require_role(['kitchen_staff', 'admin']);

$input = json_decode(file_get_contents('php://input'), true);
$date = isset($input['date']) ? $input['date'] : date('Y-m-d', strtotime('+1 day'));
$filter = isset($input['filter']) ? $input['filter'] : 'all'; // 'all', 'on', 'off'

try {
    $sql = "SELECT s.full_name, s.student_id_no, s.department, 
            COALESCE(ma.is_active, 0) as status
            FROM students s
            JOIN users u ON s.user_id = u.id
            LEFT JOIN meal_attendance ma ON s.user_id = ma.user_id AND ma.meal_date = ?
            WHERE u.is_approved = 1
            ORDER BY status DESC, s.student_id_no ASC";
            
    $stmt = $conn->prepare($sql);
    $stmt->execute([$date]);
    $all_students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $on_count = 0;
    $off_count = 0;
    $filtered_list = [];

    foreach ($all_students as $student) {
        if ($student['status'] == 1) {
            $on_count++;
        } else {
            $off_count++;
        }

        if ($filter === 'all') {
            $filtered_list[] = $student;
        } elseif ($filter === 'on' && $student['status'] == 1) {
            $filtered_list[] = $student;
        } elseif ($filter === 'off' && $student['status'] == 0) {
            $filtered_list[] = $student;
        }
    }

    $checkin_stmt = $conn->prepare("SELECT
            SUM(CASE WHEN status = 'checked_in' THEN 1 ELSE 0 END) AS checkin_count,
            SUM(CASE WHEN status = 'checked_in' AND is_active = 0 THEN 1 ELSE 0 END) AS surge_count
        FROM meal_attendance
        WHERE meal_date = ?");
    $checkin_stmt->execute([$date]);
    $checkin_row = $checkin_stmt->fetch(PDO::FETCH_ASSOC);
    $checkin_count = intval($checkin_row['checkin_count'] ?? 0);
    $surge_count = intval($checkin_row['surge_count'] ?? 0);

    echo json_encode([
        "status" => "success",
        "date" => $date,
        "on_count" => $on_count,
        "off_count" => $off_count,
        "checked_count" => $checkin_count,
        "checkin_count" => $checkin_count,
        "surge_count" => $surge_count,
        "list" => $filtered_list
    ]);

} catch (PDOException $e) { 
    echo json_encode(["status" => "error", "message" => $e->getMessage()]); 
}
?>